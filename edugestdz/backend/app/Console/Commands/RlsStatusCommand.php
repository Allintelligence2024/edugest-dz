<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 — Audit du Row Level Security PostgreSQL.
 *
 * La migration RLS applique ses politiques sous condition (`if driver !==
 * pgsql return` puis un test `tableHasColumn`). Résultat : la couverture
 * réelle était inconnue — une table pouvait silencieusement échapper au RLS
 * sans que personne ne s'en aperçoive.
 *
 * Cette commande rend la couverture observable et vérifiable en CI.
 */
class RlsStatusCommand extends Command
{
    protected $signature = 'rls:status {--json : Sortie machine} {--strict : Code de sortie non nul si une table est non protégée}';

    protected $description = 'Affiche l\'état du Row Level Security PostgreSQL table par table';

    /** Tables devant impérativement être protégées par RLS. */
    public const TABLES_ATTENDUES = [
        'eleves', 'users', 'groupes', 'cours', 'seances',
        'presences', 'evaluations', 'notes', 'bulletins',
        'factures', 'paiements', 'absences_journalieres', 'billets',
        'enseignants', 'contrats', 'personnel_non_enseignant', 'paies',
        'circuits_transport', 'transport_eleves', 'pointage_bus',
        'menus_cantine', 'inscriptions_cantine', 'repas_journaliers',
        'articles_stock', 'mouvements_stock', 'prets_materiel',
        'bons_commande', 'depenses', 'budget_previsionnel',
        'locaux_batiment', 'interventions_entretien', 'entretiens_preventifs',
        'cameras_config', 'alertes_surveillance',
        'lms_cours', 'lms_inscriptions',
        'tenant_modules', 'whatsapp_messages',
        'diagnostics_eleves', 'plans_rattrapage', 'convocations_parents',
        'signalements_comportement', 'notifications_parent',
    ];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('RLS non applicable : le driver courant n\'est pas PostgreSQL.');

            return self::SUCCESS;
        }

        $etat = $this->collecter();

        if ($this->option('json')) {
            $this->line(json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->afficher($etat);
        }

        $problemes = count($etat['non_protegees']) + count($etat['absentes']);

        if ($this->option('strict') && $problemes > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array{protegees:list<string>,non_protegees:list<string>,absentes:list<string>,details:array} */
    public function collecter(): array
    {
        $protegees = [];
        $nonProtegees = [];
        $absentes = [];
        $details = [];

        foreach (self::TABLES_ATTENDUES as $table) {
            if (!Schema::hasTable($table)) {
                $absentes[] = $table;
                continue;
            }

            $rlsActif = (bool) DB::selectOne(
                'SELECT relrowsecurity FROM pg_class WHERE relname = ?',
                [$table]
            )?->relrowsecurity;

            $nbPolicies = (int) (DB::selectOne(
                'SELECT COUNT(*) AS n FROM pg_policies WHERE tablename = ?',
                [$table]
            )?->n ?? 0);

            $details[$table] = [
                'rls_actif'  => $rlsActif,
                'nb_policies'=> $nbPolicies,
            ];

            // Une table avec RLS activé mais sans politique bloque tout ;
            // une table sans RLS n'isole rien. Les deux sont des anomalies.
            if ($rlsActif && $nbPolicies > 0) {
                $protegees[] = $table;
            } else {
                $nonProtegees[] = $table;
            }
        }

        return [
            'total_attendu'  => count(self::TABLES_ATTENDUES),
            'protegees'      => $protegees,
            'non_protegees'  => $nonProtegees,
            'absentes'       => $absentes,
            'details'        => $details,
        ];
    }

    private function afficher(array $etat): void
    {
        $this->info(sprintf(
            'RLS : %d/%d table(s) protégée(s)',
            count($etat['protegees']),
            $etat['total_attendu']
        ));

        if ($etat['absentes']) {
            $this->warn('Tables absentes du schéma (migration non jouée ?) :');
            $this->line('  ' . implode(', ', $etat['absentes']));
        }

        if ($etat['non_protegees']) {
            $this->error('Tables SANS protection RLS effective :');

            $this->table(
                ['Table', 'RLS activé', 'Politiques'],
                array_map(
                    fn ($t) => [
                        $t,
                        $etat['details'][$t]['rls_actif'] ? 'oui' : 'NON',
                        $etat['details'][$t]['nb_policies'],
                    ],
                    $etat['non_protegees']
                )
            );

            return;
        }

        if (!$etat['absentes']) {
            $this->info('✅ Toutes les tables attendues sont protégées.');
        }
    }
}
