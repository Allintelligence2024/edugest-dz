<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\GoogleClassroomConnexion;
use App\Models\GoogleCourseLiaison;
use App\Services\GoogleClassroomService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Auth;

class GoogleClassroomController extends Controller
{
    public function __construct(private GoogleClassroomService $gcService) {}

    public function auth(): JsonResponse
    {
        $user = Auth::user();
        $url = $this->gcService->authUrl(config('tenant.current_id'), $user->id);

        return response()->json(['success' => true, 'data' => ['auth_url' => $url]]);
    }

    public function callback(Request $request): JsonResponse
    {
        $state = json_decode($request->input('state', '{}'), true);
        $tenantId = $state['tenant_id'] ?? null;
        $userId   = $state['user_id'] ?? null;

        if (!$tenantId || !$userId) {
            return response()->json(['success' => false, 'error' => 'État invalide'], 400);
        }

        $code = $request->input('code');
        if (!$code) {
            return response()->json(['success' => false, 'error' => 'Code d\'autorisation manquant'], 400);
        }

        try {
            $connexion = $this->gcService->handleCallback($code, $tenantId, $userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Compte Google Classroom connecté',
                    'email'   => $connexion->email,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function status(): JsonResponse
    {
        $user = Auth::user();
        $connexion = GoogleClassroomConnexion::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'connected' => $connexion !== null,
                'email'     => $connexion?->email,
            ],
        ]);
    }

    public function revoke(): JsonResponse
    {
        $user = Auth::user();
        GoogleClassroomConnexion::where('user_id', $user->id)->delete();

        return response()->json(['success' => true, 'message' => 'Connexion Google Classroom révoquée']);
    }

    public function courses(): JsonResponse
    {
        $user = Auth::user();
        $connexion = GoogleClassroomConnexion::where('user_id', $user->id)->firstOrFail();

        $courses = $this->gcService->listCourses($connexion);

        return response()->json(['success' => true, 'data' => $courses]);
    }

    public function link(Request $request): JsonResponse
    {
        $request->validate([
            'evaluation_id' => 'required|string|exists:evaluations,id',
            'gc_course_id'  => 'required|string',
            'gc_course_name' => 'nullable|string',
        ]);

        $evaluation = Evaluation::findOrFail($request->input('evaluation_id'));

        $liaison = GoogleCourseLiaison::create([
            'evaluation_id' => $request->input('evaluation_id'),
            'gc_course_id'  => $request->input('gc_course_id'),
            'gc_course_name' => $request->input('gc_course_name'),
        ]);

        $evaluation->update(['gc_coursework_id' => $request->input('gc_course_id')]);

        return response()->json(['success' => true, 'data' => $liaison], 201);
    }

    public function links(): JsonResponse
    {
        $liaisons = GoogleCourseLiaison::with('evaluation')->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $liaisons]);
    }

    public function sync(string $id): JsonResponse
    {
        $liaison = GoogleCourseLiaison::with('evaluation')->findOrFail($id);
        $user = Auth::user();
        $connexion = GoogleClassroomConnexion::where('user_id', $user->id)->firstOrFail();

        $coursework = $this->gcService->syncCoursework($liaison, $connexion);

        return response()->json(['success' => true, 'data' => $coursework]);
    }

    public function destroyLink(string $id): JsonResponse
    {
        $liaison = GoogleCourseLiaison::findOrFail($id);
        if ($liaison->evaluation) {
            $liaison->evaluation->update(['gc_coursework_id' => null]);
        }
        $liaison->delete();

        return response()->json(['success' => true, 'message' => 'Liaison supprimée']);
    }
}
