<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AbsenceJournaliere;
use App\Models\Badge;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\PointageEnseignant;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PointageBadgeController extends BaseApiController
{
    public function __construct(private readonly SmsService $sms) {}

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'badge_uid'   => 'required|string|max:100',
            'type'        => 'required|in:entrée,sortie',
            'terminal_id' => 'nullable|string|max:50',
        ]);

        $badge = Badge::trouverParUid($validated['badge_uid']);

        if (!$badge) {
            Log::warning('PointageBadge: badge inconnu', ['uid' => $validated['badge_uid']]);
            return $this->error('Badge non reconnu ou inactif', 'BADGE_INCONNU', 404);
        }

        $heure = now()->format('H:i:s');
        $today = today();

        return match ($badge->type_proprietaire) {
            'eleve'      => $this->pointerEleve($badge, $validated['type'], $heure, $today),
            'enseignant' => $this->pointerEnseignant($badge, $validated['type'], $heure, $today),
            default      => $this->error('Type de propriétaire non géré', 'TYPE_INCONNU', 422),
        };
    }

    private function pointerEleve(Badge $badge, string $type, string $heure, $today): JsonResponse
    {
        $eleve = Eleve::find($badge->proprietaire_id);

        if (!$eleve) {
            return $this->notFound('Élève associé au badge introuvable');
        }

        $absence = AbsenceJournaliere::firstOrNew([
            'tenant_id'    => config('tenant.current_id'),
            'eleve_id'     => $eleve->id,
            'date_absence' => $today,
        ]);

        if ($type === 'entrée') {
            $heureLimite = config('etablissement.heure_limite_retard', '08:30');
            $enRetard    = $heure > $heureLimite;

            $absence->statut       = $enRetard ? 'retard' : 'present';
            $absence->heure_arrivee = $heure;
            $absence->signale_par  = 'badge';
            $absence->save();

            if ($enRetard && !$absence->sms_parent_envoye) {
                $this->envoyerSmsRetardEleve($eleve, $heure);
                $absence->update(['sms_parent_envoye' => true, 'sms_envoye_at' => now()]);
            }

            $statut  = $enRetard ? 'retard' : 'présent';
            $message = "✅ {$eleve->prenom} {$eleve->nom} — {$statut} à {$heure}";
        } else {
            $absence->statut = 'present';
            $absence->save();
            $message = "👋 {$eleve->prenom} {$eleve->nom} — sortie à {$heure}";
        }

        return $this->success([
            'personne'   => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'role'       => 'élève',
            'type'       => $type,
            'heure'      => $heure,
            'statut'     => $absence->statut,
            'absence_id' => $absence->id,
        ], $message);
    }

    private function pointerEnseignant(Badge $badge, string $type, string $heure, $today): JsonResponse
    {
        $enseignant = Enseignant::find($badge->proprietaire_id);

        if (!$enseignant) {
            return $this->notFound('Enseignant associé au badge introuvable');
        }

        $pointage = PointageEnseignant::firstOrNew([
            'tenant_id'     => config('tenant.current_id'),
            'enseignant_id' => $enseignant->id,
            'date'          => $today,
        ]);

        if ($type === 'entrée') {
            if ($pointage->heure_arrivee) {
                return $this->error(
                    'Arrivée déjà enregistrée à ' . $pointage->heure_arrivee,
                    'DEJA_POINTE',
                    409
                );
            }

            $heureLimite = config('etablissement.heure_limite_retard_prof', '08:15');
            $enRetard    = $heure > $heureLimite;

            $pointage->heure_arrivee = $heure;
            $pointage->methode       = 'badge';
            $pointage->badge_uid     = $badge->badge_uid;
            $pointage->statut        = $enRetard ? 'retard' : 'present';
            $pointage->save();

            $statut  = $enRetard ? 'retard' : 'présent';
            $message = "✅ Prof {$enseignant->nom} {$enseignant->prenom} — {$statut} à {$heure}";

        } else {
            if (!$pointage->heure_arrivee) {
                return $this->error('Aucune arrivée enregistrée pour ce jour', 'PAS_ARRIVEE', 422);
            }

            $pointage->heure_depart = $heure;
            $pointage->save();

            $message = "👋 Prof {$enseignant->nom} {$enseignant->prenom} — sortie à {$heure}";
        }

        return $this->success([
            'personne'    => ['nom' => $enseignant->nom, 'prenom' => $enseignant->prenom],
            'role'        => 'enseignant',
            'type'        => $type,
            'heure'       => $heure,
            'statut'      => $pointage->statut,
            'pointage_id' => $pointage->id,
        ], $message);
    }

    private function envoyerSmsRetardEleve(Eleve $eleve, string $heure): void
    {
        $message = "EduGest DZ : Votre enfant {$eleve->prenom} {$eleve->nom} "
                 . "est arrivé en retard à {$heure}. Merci.";

        foreach ($eleve->parents as $parent) {
            if ($parent->telephone_1) {
                try {
                    $this->sms->send($parent->telephone_1, $message);
                } catch (\Throwable $e) {
                    Log::error('SMS retard élève échoué', [
                        'eleve_id' => $eleve->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
