<?php
namespace App\Services;

use App\Models\{Eleve, Note, Presence};
use Illuminate\Support\Facades\Cache;

class EleveService
{
    /** Préfixe de format des jetons QR (permet de faire évoluer le schéma). */
    private const QR_PREFIX = 'EGQR1';

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
            $eleve->forceFill(['qr_code' => $path])->save();
        } catch (\Throwable $e) {
            \Log::warning('QR Code generation skipped: ' . $e->getMessage());
        }
    }

    /**
     * Génère (et persiste) le jeton QR de présence d'un élève.
     *
     * Le jeton est signé par HMAC-SHA256 avec une clé DÉDIÉE (config
     * security.qr.signing_key), indépendante de APP_KEY : faire tourner
     * APP_KEY n'invalide pas les badges déjà imprimés.
     *
     * Format : "EGQR1.<payload base64url>.<signature base64url>"
     * Le payload est déterministe (pas de now()) : régénérer le jeton pour un
     * même élève et une même version produit exactement la même chaîne, ce qui
     * rend la vérification idempotente.
     */
    public function genererTokenQR(Eleve $eleve): string
    {
        $version = (int) ($eleve->qr_version ?: 1);

        $payload = [
            'v' => $version,
            'e' => (string) $eleve->id,
            't' => (string) $eleve->tenant_id,
        ];

        $body      = $this->b64url(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = $this->b64url(hash_hmac('sha256', $body, $this->qrSigningKey(), true));
        $token     = self::QR_PREFIX . '.' . $body . '.' . $signature;

        // On stocke l'empreinte du jeton, jamais le jeton lui-même :
        // une fuite de la base ne permet pas de fabriquer un badge valide.
        $hash = hash('sha256', $token);

        if ($eleve->qr_token_hash !== $hash || $eleve->qr_generated_at === null) {
            $eleve->forceFill([
                'qr_token_hash'   => $hash,
                'qr_version'      => $version,
                'qr_generated_at' => now(),
            ])->save();
        }

        return $token;
    }

    /**
     * Révoque le badge courant d'un élève (perte/vol) en incrémentant sa
     * version : l'ancien jeton ne correspondra plus à aucune empreinte.
     */
    public function revoquerTokenQR(Eleve $eleve): string
    {
        $eleve->forceFill([
            'qr_version'    => (int) ($eleve->qr_version ?: 1) + 1,
            'qr_token_hash' => null,
        ])->save();

        return $this->genererTokenQR($eleve->refresh());
    }

    /**
     * Vérifie un jeton QR et retourne son payload, ou null si invalide.
     *
     * Coût : O(1) — un seul index scan sur (tenant_id, qr_token_hash).
     */
    public function verifierTokenQR(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3 || $parts[0] !== self::QR_PREFIX) {
            return null;
        }

        [, $body, $signature] = $parts;

        // 1. Vérification cryptographique de la signature (temps constant).
        $attendu = $this->b64url(hash_hmac('sha256', $body, $this->qrSigningKey(), true));

        if (!hash_equals($attendu, $signature)) {
            return null;
        }

        $payload = json_decode($this->b64urlDecode($body), true);

        if (!is_array($payload) || empty($payload['e']) || empty($payload['t'])) {
            return null;
        }

        // 2. Le jeton doit appartenir au tenant courant : une signature valide
        //    d'un autre établissement ne donne aucun accès ici.
        $tenantCourant = config('tenant.current_id');

        if ($tenantCourant !== null && (string) $payload['t'] !== (string) $tenantCourant) {
            return null;
        }

        // 3. Lookup indexé sur l'empreinte : garantit que le jeton est bien
        //    celui actuellement actif (révocation par qr_version).
        $eleve = Eleve::withoutGlobalScope('tenant')
            ->where('tenant_id', $payload['t'])
            ->where('qr_token_hash', hash('sha256', $token))
            ->first();

        if (!$eleve) {
            return null;
        }

        // 4. Expiration éventuelle du badge.
        $ttlDays = config('security.qr.ttl_days');

        if ($ttlDays && $eleve->qr_generated_at
            && now()->greaterThan($eleve->qr_generated_at->copy()->addDays((int) $ttlDays))) {
            return null;
        }

        return [
            'eleve'  => $eleve->id,
            'tenant' => $eleve->tenant_id,
            'nom'    => "{$eleve->nom} {$eleve->prenom}",
        ];
    }

    private function qrSigningKey(): string
    {
        $key = (string) config('security.qr.signing_key');

        // APP_KEY Laravel est préfixée "base64:" — on la décode pour disposer
        // des 32 octets d'entropie réels.
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7)) ?: $key;
        }

        return $key;
    }

    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'));
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
