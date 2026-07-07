<?php
namespace App\Services;

use App\Models\GoogleClassroomConnexion;
use App\Models\GoogleCourseLiaison;
use App\Models\GoogleSyncLog;
use Google\Client as GoogleClient;
use Google\Service\Classroom;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GoogleClassroomService
{
    private ?GoogleClient $client = null;
    private ?Classroom $classroom = null;

    public function client(): GoogleClient
    {
        if ($this->client === null) {
            $this->client = new GoogleClient();
            $this->client->setClientId(config('services.google.client_id'));
            $this->client->setClientSecret(config('services.google.client_secret'));
            $this->client->setRedirectUri(config('services.google.redirect_uri'));
            $this->client->setScopes([
                Classroom::CLASSROOM_COURSES_READONLY,
                Classroom::CLASSROOM_COURSEWORK_STUDENTS,
                Classroom::CLASSROOM_STUDENT_SUBMISSIONS_STUDENTS_READONLY,
            ]);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');
        }

        return $this->client;
    }

    public function classroom(): Classroom
    {
        if ($this->classroom === null) {
            $this->classroom = new Classroom($this->client());
        }

        return $this->classroom;
    }

    public function authUrl(string $tenantId, string $userId): string
    {
        $this->client()->setState(json_encode([
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
        ]));

        return $this->client()->createAuthUrl();
    }

    public function handleCallback(string $code, string $tenantId, string $userId): GoogleClassroomConnexion
    {
        $this->client()->fetchAccessTokenWithAuthCode($code);
        $token = $this->client()->getAccessToken();

        $oauth2 = new \Google\Service\Oauth2($this->client());
        $userInfo = $oauth2->userinfo->get();

        $connexion = GoogleClassroomConnexion::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            [
                'email'          => $userInfo->email,
                'token'          => Crypt::encryptString(json_encode($token)),
                'expires_at'     => now()->addSeconds($token['expires_in'] ?? 3600),
                'google_user_id' => $userInfo->id,
            ]
        );

        return $connexion;
    }

    public function restoreToken(GoogleClassroomConnexion $connexion): bool
    {
        $token = json_decode(Crypt::decryptString($connexion->token), true);

        if ($token === null) {
            return false;
        }

        $this->client()->setAccessToken($token);

        if ($this->client()->isAccessTokenExpired()) {
            if ($refreshToken = $this->client()->getRefreshToken()) {
                $this->client()->fetchAccessTokenWithRefreshToken($refreshToken);
                $newToken = $this->client()->getAccessToken();
                $connexion->update([
                    'token'     => Crypt::encryptString(json_encode($newToken)),
                    'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ]);

                return true;
            }

            return false;
        }

        return true;
    }

    public function listCourses(GoogleClassroomConnexion $connexion): array
    {
        if (!$this->restoreToken($connexion)) {
            return [];
        }

        $courses = [];
        $pageToken = null;

        do {
            $params = ['pageSize' => 100];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->classroom()->courses->listCourses($params);

            foreach ($response->getCourses() ?? [] as $course) {
                $courses[] = [
                    'id'   => $course->getId(),
                    'name' => $course->getName(),
                    'section' => $course->getSection(),
                    'state'   => $course->getCourseState(),
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $courses;
    }

    public function syncCoursework(GoogleCourseLiaison $liaison, GoogleClassroomConnexion $connexion): array
    {
        if (!$this->restoreToken($connexion)) {
            return $this->logSync($liaison, 'sync_coursework', 'error', 'Token expired');
        }

        try {
            $courseWork = $this->classroom()->courses_courseWork->listCoursesCourseWork($liaison->gc_course_id);

            $results = [];
            foreach ($courseWork->getCourseWork() ?? [] as $cw) {
                $results[] = [
                    'id'          => $cw->getId(),
                    'title'       => $cw->getTitle(),
                    'description' => $cw->getDescription(),
                    'due_date'    => $cw->getDueDate(),
                    'max_points'  => $cw->getMaxPoints(),
                ];
            }

            $this->logSync($liaison, 'sync_coursework', 'success', 'Coursework synced', ['count' => count($results)]);

            return $results;
        } catch (\Throwable $e) {
            $this->logSync($liaison, 'sync_coursework', 'error', $e->getMessage());

            return [];
        }
    }

    private function logSync(GoogleCourseLiaison $liaison, string $action, string $status, string $message, ?array $meta = null): void
    {
        GoogleSyncLog::create([
            'liaison_id' => $liaison->id,
            'tenant_id'  => $liaison->tenant_id,
            'action'     => $action,
            'status'     => $status,
            'message'    => $message,
            'meta'       => $meta,
        ]);

        if ($status === 'success') {
            $liaison->update(['last_sync_at' => now()]);
        }
    }
}
