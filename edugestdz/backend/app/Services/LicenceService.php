<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service de vérification de licence pour le mode Self-Hosted (Niveau 3).
 * En mode SaaS (Niveau 1 et 2), ce service est désactivé.
 */
class LicenceService
{
    /**
     * Vérifier si la licence est valide.
     * Appelé au démarrage et via le scheduler hebdomadaire.
     */
    public function verifier(): array
    {
        // En mode SaaS (multi-tenant), aucune vérification locale
        if (config('tenant.mode', 'multi') !== 'single') {
            return ['valide' => true, 'mode' => 'saas'];
        }

        $licenceKey    = config('app.licence_key', env('LICENSE_KEY', ''));
        $licenceExpiry = config('app.licence_expiry', env('LICENSE_EXPIRY', ''));
        $tenantId      = config('tenant.current_id', env('TENANT_ID', ''));

        // Pas de clé configurée
        if (empty($licenceKey)) {
            Log::warning('EduGest DZ: Aucune clé de licence configurée (MODE ÉVALUATION)');
            return [
                'valide'  => true, // Tolérant en évaluation
                'mode'    => 'evaluation',
                'message' => 'Mode évaluation — configurer LICENSE_KEY pour activer',
            ];
        }

        // Vérifier la date d'expiration
        if (!empty($licenceExpiry)) {
            try {
                $expiry = Carbon::parse($licenceExpiry);
                if ($expiry->isPast()) {
                    Log::error("EduGest DZ: Licence expirée le {$licenceExpiry}");
                    return [
                        'valide'   => false,
                        'mode'     => 'expired',
                        'message'  => "Licence expirée le {$licenceExpiry}. Contacter support@edugest.dz",
                        'expiry'   => $licenceExpiry,
                    ];
                }

                $daysLeft = now()->diffInDays($expiry, false);
                if ($daysLeft <= 30) {
                    Log::warning("EduGest DZ: Licence expire dans {$daysLeft} jours");
                }

                return [
                    'valide'    => true,
                    'mode'      => 'licensed',
                    'days_left' => $daysLeft,
                    'expiry'    => $licenceExpiry,
                    'tenant'    => $tenantId,
                ];

            } catch (\Throwable $e) {
                Log::warning('EduGest DZ: Date de licence invalide');
            }
        }

        return ['valide' => true, 'mode' => 'licensed'];
    }

    /**
     * Obtenir les informations de l'installation.
     */
    public function getInfo(): array
    {
        return [
            'mode'         => config('tenant.mode', 'multi'),
            'tenant_name'  => env('TENANT_NAME', 'Non configuré'),
            'tenant_wilaya'=> env('TENANT_WILAYA', ''),
            'licence_key'  => env('LICENSE_KEY') ? '***' . substr(env('LICENSE_KEY'), -4) : 'Non configuré',
            'expiry'       => env('LICENSE_EXPIRY', 'Non défini'),
            'version'      => config('app.version', '1.0.0'),
            'installed_at' => file_exists(storage_path('app/.installed'))
                ? file_get_contents(storage_path('app/.installed'))
                : 'Inconnu',
        ];
    }
}
