<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Cache;

class TenantModule extends Model
{
    use HasUuids;

    protected $table = 'tenant_modules';

    protected $fillable = [
        'tenant_id', 'module_key', 'actif',
        'desactive_le', 'modifie_par', 'raison',
    ];

    protected $casts = [
        'actif'        => 'boolean',
        'desactive_le' => 'datetime',
    ];

    public const MODULES = [
        'core' => [
            'key'          => 'core',
            'label'        => 'Fonctions de base',
            'description'  => 'Dashboard, Élèves, Planning, Notes, Bulletins, Factures — toujours actif',
            'icon'         => '🏫',
            'obligatoire'  => true,
            'categorie'    => 'core',
            'routes_api'   => [],
            'route_front'  => null,
            'plan_minimum' => 'starter',
        ],
        'transport' => [
            'key'          => 'transport',
            'label'        => 'Transport Scolaire',
            'description'  => 'Circuits de bus, pointage élèves, facturation transport mensuelle',
            'icon'         => '🚌',
            'obligatoire'  => false,
            'categorie'    => 'gestion',
            'routes_api'   => ['/api/v1/transport'],
            'route_front'  => '/transport',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Écoles avec service de bus scolaire',
        ],
        'cantine' => [
            'key'          => 'cantine',
            'label'        => 'Cantine / Restauration',
            'description'  => 'Menus hebdomadaires, inscriptions, pointage repas, stock cuisine',
            'icon'         => '🍽️',
            'obligatoire'  => false,
            'categorie'    => 'gestion',
            'routes_api'   => ['/api/v1/cantine'],
            'route_front'  => '/cantine',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Écoles avec service de restauration',
        ],
        'stock' => [
            'key'          => 'stock',
            'label'        => 'Stock & Inventaire',
            'description'  => 'Gestion des articles, mouvements, alertes seuil bas, bons de commande',
            'icon'         => '📦',
            'obligatoire'  => false,
            'categorie'    => 'gestion',
            'routes_api'   => ['/api/v1/stock'],
            'route_front'  => '/stock',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Écoles avec stocks matériels importants',
        ],
        'budget' => [
            'key'          => 'budget',
            'label'        => 'Budget & Dépenses',
            'description'  => 'Budget prévisionnel, dépenses par catégorie, bilan annuel',
            'icon'         => '📈',
            'obligatoire'  => false,
            'categorie'    => 'finance',
            'routes_api'   => ['/api/v1/budget'],
            'route_front'  => '/budget',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Directeurs souhaitant suivre les finances en détail',
        ],
        'personnel' => [
            'key'          => 'personnel',
            'label'        => 'Personnel Non-Enseignant',
            'description'  => 'Agents, congés, paie IRG/CNAS, pointage du personnel admin',
            'icon'         => '👷',
            'obligatoire'  => false,
            'categorie'    => 'rh',
            'routes_api'   => ['/api/v1/personnel'],
            'route_front'  => '/personnel-admin',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Écoles avec personnel administratif',
        ],
        'entretien' => [
            'key'          => 'entretien',
            'label'        => 'Entretien Bâtiment',
            'description'  => 'Locaux, tickets d\'intervention, maintenance préventive',
            'icon'         => '🔧',
            'obligatoire'  => false,
            'categorie'    => 'gestion',
            'routes_api'   => ['/api/v1/entretien'],
            'route_front'  => '/entretien',
            'plan_minimum' => 'premium',
            'pour_qui'     => 'Établissements avec bâtiments à entretenir',
        ],
        'surveillance' => [
            'key'          => 'surveillance',
            'label'        => 'Surveillance Dahua',
            'description'  => 'Alertes webhook caméras Dahua, notifications SMS si intrusion',
            'icon'         => '🔒',
            'obligatoire'  => false,
            'categorie'    => 'securite',
            'routes_api'   => ['/api/v1/surveillance'],
            'route_front'  => '/surveillance',
            'plan_minimum' => 'premium',
            'pour_qui'     => 'Écoles équipées de caméras Dahua DVR/NVR',
        ],
        'lms' => [
            'key'          => 'lms',
            'label'        => 'LMS — Cours en ligne',
            'description'  => 'Cours vidéo, PDF, quiz, devoirs, certificats de complétion',
            'icon'         => '🖥️',
            'obligatoire'  => false,
            'categorie'    => 'pedagogie',
            'routes_api'   => ['/api/v1/lms'],
            'route_front'  => '/lms',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Centres de cours avec contenu pédagogique en ligne',
        ],
        'marketplace' => [
            'key'          => 'marketplace',
            'label'        => 'Marketplace & Réservations',
            'description'  => 'Profil public du centre, réservations parents, avis et notations',
            'icon'         => '🛒',
            'obligatoire'  => false,
            'categorie'    => 'marketing',
            'routes_api'   => ['/api/v1/marketplace'],
            'route_front'  => '/centres',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Centres souhaitant être référencés',
        ],
        'examens' => [
            'key'          => 'examens',
            'label'        => 'Examens Officiels BEM/BAC',
            'description'  => 'Organisation BEM/BAC, salles, affectation surveillants, convocations PDF',
            'icon'         => '🎓',
            'obligatoire'  => false,
            'categorie'    => 'pedagogie',
            'routes_api'   => ['/api/v1/examens'],
            'route_front'  => '/examens',
            'plan_minimum' => 'premium',
            'pour_qui'     => 'Lycées et CEM organisant des examens officiels',
        ],
        'diagnostic' => [
            'key'          => 'diagnostic',
            'label'        => 'Diagnostic Niveau (EWS)',
            'description'  => 'Early Warning System, score de risque, rattrapages, convocations parents',
            'icon'         => '🔬',
            'obligatoire'  => false,
            'categorie'    => 'pedagogie',
            'routes_api'   => ['/api/v1/diagnostic'],
            'route_front'  => '/diagnostic',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Établissements souhaitant un suivi pédagogique avancé',
        ],
        'billets' => [
            'key'          => 'billets',
            'label'        => 'Billets & Tickets',
            'description'  => 'Billets d\'entrée tardive, sortie anticipée, convocation interne',
            'icon'         => '🎫',
            'obligatoire'  => false,
            'categorie'    => 'vie_scolaire',
            'routes_api'   => ['/api/v1/billets'],
            'route_front'  => '/billets',
            'plan_minimum' => 'starter',
            'pour_qui'     => 'Écoles avec système de billets disciplinaires',
        ],
        'pointage' => [
            'key'          => 'pointage',
            'label'        => 'Pointage Enseignants',
            'description'  => 'Pointage arrivée/départ enseignants, badges RFID, rapports',
            'icon'         => '🏷️',
            'obligatoire'  => false,
            'categorie'    => 'rh',
            'routes_api'   => ['/api/v1/pointage'],
            'route_front'  => '/pointage',
            'plan_minimum' => 'standard',
            'pour_qui'     => 'Écoles souhaitant suivre la ponctualité des enseignants',
        ],
        'bibliotheque' => [
            'key'          => 'bibliotheque',
            'label'        => 'Bibliothèque Scolaire',
            'description'  => 'Catalogue, prêts/retours, amendes automatiques, réservations',
            'icon'         => '📚',
            'obligatoire'  => false,
            'categorie'    => 'gestion',
            'routes_api'   => ['/api/v1/bibliotheque'],
            'route_front'  => '/bibliotheque',
            'plan_minimum' => 'premium',
            'pour_qui'     => 'Écoles disposant d\'une bibliothèque',
        ],
    ];

    public static function getActifs(string $tenantId): array
    {
        return Cache::remember("modules_actifs_{$tenantId}", 300, function () use ($tenantId) {
            return self::where('tenant_id', $tenantId)
                ->where('actif', true)
                ->pluck('module_key')
                ->toArray();
        });
    }

    public static function estActif(string $tenantId, string $moduleKey): bool
    {
        // Les modules 'obligatoire' sont toujours actifs
        if (isset(self::MODULES[$moduleKey]) && self::MODULES[$moduleKey]['obligatoire']) {
            return true;
        }

        // ── MODULES DÉSACTIVÉS PAR DÉFAUT ──────────────────────────────
        // Ces modules nécessitent une configuration explicite avant activation
        $desactivesParDefaut = [
            'marketplace',  // Nécessite profil public + Satim production
            'surveillance', // Nécessite caméras Dahua physiques
        ];

        // Si pas de record en BDD ET module désactivé par défaut → false
        $existeEnBdd = self::where('tenant_id', $tenantId)
            ->where('module_key', $moduleKey)
            ->exists();

        if (!$existeEnBdd && in_array($moduleKey, $desactivesParDefaut)) {
            return false; // Inactif par défaut
        }

        // Si pas de record en BDD pour les autres modules → actif par défaut
        if (!$existeEnBdd) {
            return true;
        }

        $actifs = self::getActifs($tenantId);
        return in_array($moduleKey, $actifs);
    }

    public static function activer(string $tenantId, string $moduleKey, ?string $userId = null): self
    {
        $module = self::updateOrCreate(
            ['tenant_id' => $tenantId, 'module_key' => $moduleKey],
            ['actif' => true, 'desactive_le' => null, 'modifie_par' => $userId]
        );

        Cache::forget("modules_actifs_{$tenantId}");
        return $module;
    }

    public static function desactiver(string $tenantId, string $moduleKey, ?string $userId = null, ?string $raison = null): self
    {
        if (isset(self::MODULES[$moduleKey]) && self::MODULES[$moduleKey]['obligatoire']) {
            throw new \RuntimeException("Le module '{$moduleKey}' est obligatoire et ne peut pas être désactivé.");
        }

        $module = self::updateOrCreate(
            ['tenant_id' => $tenantId, 'module_key' => $moduleKey],
            [
                'actif'        => false,
                'desactive_le' => now(),
                'modifie_par'  => $userId,
                'raison'       => $raison,
            ]
        );

        Cache::forget("modules_actifs_{$tenantId}");
        return $module;
    }

    public static function getEtatComplet(string $tenantId): array
    {
        $configures = self::where('tenant_id', $tenantId)
            ->get()
            ->keyBy('module_key');

        return collect(self::MODULES)->map(function ($def, $key) use ($configures) {
            $config = $configures->get($key);
            return [
                ...$def,
                'actif'        => $config ? (bool) $config->actif : true,
                'desactive_le' => $config?->desactive_le,
                'raison'       => $config?->raison,
            ];
        })->values()->toArray();
    }
}
