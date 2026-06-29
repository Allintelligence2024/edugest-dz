<?php
namespace App\Services;

use App\Models\{Eleve, Note, Presence};
use Illuminate\Support\Facades\Cache;

class EleveService
{
    public function genererNumero(): string
    {
        $tenantId = config('tenant.current_id');
        $annee    = now()->year;
        $prefix   = "EL-{$annee}-";

        return Cache::lock("numero_inscription_{$tenantId}", 5)->block(3, function () use ($tenantId, $annee, $prefix) {
            $last = Eleve::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('numero_inscription', 'LIKE', "{$prefix}%")
                ->max('numero_inscription');

            $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;
            return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }

    public function genererQRCode(Eleve $eleve): void
    {
        $token = $this->genererTokenQR($eleve);

        try {
            $qr   = \QrCode::format('png')->size(300)->generate(json_encode(['token' => $token]));
            $path = "qrcodes/eleves/{$eleve->tenant_id}/{$eleve->id}.png";
            \Storage::disk('public')->put($path, $qr);
            \Storage::disk('public')->put("qrcodes/eleves/{$eleve->tenant_id}/{$eleve->id}.token", $token);
            $eleve->update(['qr_code' => $path]);
        } catch (\Throwable $e) {
            \Log::warning('QR Code generation skipped: ' . $e->getMessage());
        }
    }

    public function genererTokenQR(Eleve $eleve): string
    {
        $payload = json_encode([
            'eleve'  => $eleve->id,
            'tenant' => $eleve->tenant_id,
            'nom'    => "{$eleve->nom} {$eleve->prenom}",
            'iat'    => now()->timestamp,
        ]);

        return \Hash::make($payload . config('app.key') . $eleve->id);
    }

    public function verifierTokenQR(string $token): ?array
    {
        // Parcourt les tokens stockés (en production, utiliser cache/index)
        $eleves = Eleve::where('tenant_id', config('tenant.current_id'))
            ->whereNotNull('qr_code')
            ->get();

        foreach ($eleves as $eleve) {
            if (\Hash::check(
                json_encode([
                    'eleve'  => $eleve->id,
                    'tenant' => $eleve->tenant_id,
                    'nom'    => "{$eleve->nom} {$eleve->prenom}",
                    'iat'    => $eleve->updated_at->timestamp,
                ]) . config('app.key') . $eleve->id,
                $token
            )) {
                return ['eleve' => $eleve->id, 'tenant' => $eleve->tenant_id, 'nom' => "{$eleve->nom} {$eleve->prenom}"];
            }
        }

        return null;
    }

    public function calculerMoyenne(?string $eleveId, ?string $groupeId = null, ?string $trimestre = null): float
    {
        $notes = Note::where('eleve_id', $eleveId)
            ->whereNotNull('note')
            ->where('absent', false)
            ->with('evaluation:id,groupe_id,note_sur,coefficient,trimestre')
            ->when($groupeId, fn($q) =>
                $q->whereHas('evaluation', fn($e) => $e->where('groupe_id', $groupeId))
            )
            ->when($trimestre, fn($q) =>
                $q->whereHas('evaluation', fn($e) => $e->where('trimestre', $trimestre))
            )
            ->get();

        if ($notes->isEmpty()) return 0.0;

        $somme  = 0;
        $coeffs = 0;

        foreach ($notes as $note) {
            $noteNormalisee = ($note->note / $note->evaluation->note_sur) * 20;
            $coeff          = $note->evaluation->coefficient;
            $somme         += $noteNormalisee * $coeff;
            $coeffs        += $coeff;
        }

        return $coeffs > 0 ? round($somme / $coeffs, 2) : 0.0;
    }

    public function calculerTauxPresence(string $eleveId, ?int $mois = null): float
    {
        $query = Presence::where('eleve_id', $eleveId)
            ->when($mois, fn($q) => $q->whereMonth('created_at', $mois));

        $total    = $query->count();
        $presents = (clone $query)
            ->whereIn('statut', ['présent', 'retard'])->count();

        return $total > 0 ? round(($presents / $total) * 100, 1) : 0.0;
    }

    public function getStatsAcademiques(Eleve $eleve): array
    {
        return [
            'moyenne_generale' => $this->calculerMoyenne($eleve->id),
            'taux_presence'    => $this->calculerTauxPresence($eleve->id),
            'nb_inscriptions'  => $eleve->inscriptions()->where('statut', 'validée')->count(),
            'nb_evaluations'   => $eleve->notes()->count(),
        ];
    }
}
