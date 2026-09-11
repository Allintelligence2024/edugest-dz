<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Console\Command;

/**
 * PILOTE-30OCT — verrouille un tenant en configuration pilote (core uniquement).
 *
 * Contexte : `estActif()` / `actifs()` sont fail-open par défaut (sans ligne
 * BDD, tout module optionnel est actif). Inverser le défaut casserait les
 * tenants existants et de nombreux tests — chantier post-pilote. En attendant,
 * le tenant pilote est verrouillé explicitement par cette commande.
 *
 * Idempotent : relançable sans effet de bord (updateOrCreate + purge cache).
 */
class ConfigurerTenantPiloteCommand extends Command
{
    protected $signature = 'tenant:configurer-pilote {tenant : ID (UUID) du tenant} {--verifier : verifie seulement, sans rien modifier}';

    protected $description = 'Verrouille un tenant en configuration pilote (core uniquement)';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error('Tenant introuvable : ' . $this->argument('tenant'));

            return 1;
        }

        if ($this->option('verifier')) {
            $actifs = $this->clesActives($tenant->id);
            $this->info('Modules actifs : ' . implode(', ', $actifs));

            return $actifs === ['core'] ? 0 : 1;
        }

        $optionnels = array_keys(array_filter(
            TenantModule::MODULES,
            fn (array $def) => !($def['obligatoire'] ?? false)
        ));

        foreach ($optionnels as $key) {
            TenantModule::desactiver($tenant->id, $key, null, 'Pilote 30 octobre — core uniquement');
        }

        $this->info('Tenant pilote verrouillé : ' . $tenant->id);
        $this->info('Modules désactivés (' . count($optionnels) . ') : ' . implode(', ', $optionnels));
        $this->info('Actifs restants : core');

        return 0;
    }

    /** Miroir de ModuleController::actifs (toutes clés sauf actif=false explicite). */
    private function clesActives(string $tenantId): array
    {
        $desactives = TenantModule::where('tenant_id', $tenantId)
            ->where('actif', false)
            ->pluck('module_key')
            ->all();

        return array_values(array_diff(array_keys(TenantModule::MODULES), $desactives));
    }
}
