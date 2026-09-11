<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationInAppService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function __construct(private NotificationInAppService $notif) {}

    public function statut(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $tenant   = DB::table('tenants')->where('id', $tenantId)->first();

        if (!$tenant || !isset($tenant->onboarding_complete)) {
            return response()->json([
                'success'     => true,
                'etape'       => 0,
                'complete'    => false,
                'progression' => ['matieres' => 0, 'enseignants' => 0, 'groupes' => 0, 'eleves' => 0],
                'etapes'      => [
                    ['id' => 1, 'label' => 'Créer une matière',        'complete' => false],
                    ['id' => 2, 'label' => 'Ajouter un enseignant',    'complete' => false],
                    ['id' => 3, 'label' => 'Créer un groupe',          'complete' => false],
                    ['id' => 4, 'label' => 'Inscrire un élève',        'complete' => false],
                    ['id' => 5, 'label' => 'Tester une notification',  'complete' => false],
                ],
            ]);
        }

        $etape    = (int) ($tenant->onboarding_etape ?? 0);
        $complete = (bool)($tenant->onboarding_complete ?? false);

        $progression = [
            'matieres'    => DB::table('matieres')->where('tenant_id', $tenantId)->count(),
            'enseignants' => DB::table('enseignants')->where('tenant_id', $tenantId)->count(),
            'groupes'     => DB::table('groupes')->where('tenant_id', $tenantId)->count(),
            'eleves'      => DB::table('eleves')->where('tenant_id', $tenantId)->count(),
        ];

        $etapeReelle = 0;
        if ($progression['matieres'] > 0)    $etapeReelle = max($etapeReelle, 1);
        if ($progression['enseignants'] > 0) $etapeReelle = max($etapeReelle, 2);
        if ($progression['groupes'] > 0)     $etapeReelle = max($etapeReelle, 3);
        if ($progression['eleves'] > 0)      $etapeReelle = max($etapeReelle, 4);
        if ($complete)                       $etapeReelle = 5;

        return response()->json([
            'success'     => true,
            'etape'       => $etapeReelle,
            'complete'    => $complete,
            'progression' => $progression,
            'etapes'      => [
                ['id' => 1, 'label' => 'Créer une matière',        'complete' => $progression['matieres'] > 0],
                ['id' => 2, 'label' => 'Ajouter un enseignant',    'complete' => $progression['enseignants'] > 0],
                ['id' => 3, 'label' => 'Créer un groupe',          'complete' => $progression['groupes'] > 0],
                ['id' => 4, 'label' => 'Inscrire un élève',        'complete' => $progression['eleves'] > 0],
                ['id' => 5, 'label' => 'Tester une notification',  'complete' => $complete],
            ],
        ]);
    }

    public function avancer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'etape' => 'required|integer|between:1,5',
        ]);

        DB::table('tenants')
            ->where('id', config('tenant.current_id'))
            ->update(['onboarding_etape' => $validated['etape']]);

        return response()->json(['success' => true, 'etape' => $validated['etape']]);
    }

    public function testerNotification(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $userId   = auth('api')->id();

        $this->notif->creer(
            userId:   $userId,
            type:     'onboarding_test',
            titre:    'EduGest DZ est prêt !',
            corps:    'Félicitations ! Votre établissement est configuré. Les notifications fonctionnent correctement.',
            meta:     ['action_url' => '/dashboard'],
            tenantId: $tenantId
        );

        DB::table('tenants')->where('id', $tenantId)->update([
            'onboarding_etape'       => 5,
            'onboarding_complete'    => true,
            'onboarding_complete_le' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Félicitations ! EduGest DZ est prêt à être utilisé.',
        ]);
    }

    public function ignorer(): JsonResponse
    {
        DB::table('tenants')
            ->where('id', config('tenant.current_id'))
            ->update(['onboarding_complete' => true, 'onboarding_complete_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Onboarding ignoré.']);
    }
}
