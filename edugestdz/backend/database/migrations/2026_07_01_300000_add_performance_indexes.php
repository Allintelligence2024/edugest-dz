<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexSafe('eleves', ['tenant_id', 'statut'], 'idx_eleves_tenant_statut');
        $this->addIndexSafe('eleves', ['tenant_id', 'niveau_scolaire'], 'idx_eleves_tenant_niveau');
        $this->addIndexSafe('eleves', ['tenant_id', 'created_at'], 'idx_eleves_tenant_created');

        $this->addIndexSafe('presences', ['eleve_id', 'statut'], 'idx_presences_eleve_statut');
        $this->addIndexSafe('presences', ['seance_id', 'eleve_id'], 'idx_presences_seance_eleve');

        $this->addIndexSafe('factures', ['tenant_id', 'statut'], 'idx_factures_tenant_statut');
        $this->addIndexSafe('factures', ['tenant_id', 'date_echeance'], 'idx_factures_tenant_echeance');
        $this->addIndexSafe('factures', ['eleve_id', 'statut'], 'idx_factures_eleve_statut');
        $this->addIndexSafe('factures', ['mois', 'annee', 'tenant_id'], 'idx_factures_periode');

        $this->addIndexSafe('paiements', ['tenant_id', 'statut'], 'idx_paiements_tenant_statut');
        $this->addIndexSafe('paiements', ['tenant_id', 'date_paiement'], 'idx_paiements_tenant_date');
        $this->addIndexSafe('paiements', ['facture_id', 'statut'], 'idx_paiements_facture_statut');

        $this->addIndexSafe('absences_journalieres', ['tenant_id', 'date_absence'], 'idx_absences_tenant_date');
        $this->addIndexSafe('absences_journalieres', ['eleve_id', 'date_absence'], 'idx_absences_eleve_date');
        $this->addIndexSafe('absences_journalieres', ['tenant_id', 'statut', 'date_absence'], 'idx_absences_tenant_statut_date');

        $this->addIndexSafe('seances', ['date_seance', 'statut'], 'idx_seances_date_statut');
        $this->addIndexSafe('seances', ['cours_id', 'date_seance'], 'idx_seances_cours_date');

        $this->addIndexSafe('inscriptions', ['groupe_id', 'statut'], 'idx_inscriptions_groupe_statut');
        $this->addIndexSafe('inscriptions', ['eleve_id', 'statut'], 'idx_inscriptions_eleve_statut');

        $this->addIndexSafe('notes', ['eleve_id', 'evaluation_id'], 'idx_notes_eleve_eval');

        $this->addIndexSafe('transport_eleves', ['circuit_id', 'actif'], 'idx_transport_circuit_actif');
        $this->addIndexSafe('transport_eleves', ['eleve_id', 'actif'], 'idx_transport_eleve_actif');
        $this->addIndexSafe('pointage_bus', ['circuit_id', 'date', 'trajet'], 'idx_pointage_bus_circuit_date');

        $this->addIndexSafe('articles_stock', ['tenant_id', 'actif', 'categorie'], 'idx_stock_tenant_actif_cat');
        $this->addIndexSafe('mouvements_stock', ['article_id', 'date_mouvement'], 'idx_mvt_article_date');

        $this->addIndexSafe('personnel_non_enseignant', ['tenant_id', 'statut', 'poste'], 'idx_personnel_tenant_statut');
        $this->addIndexSafe('pointage_personnel', ['agent_id', 'date'], 'idx_pointage_personnel_date');

        $this->addIndexSafe('interventions_entretien', ['tenant_id', 'statut', 'priorite'], 'idx_interventions_statut');
        $this->addIndexSafe('entretiens_preventifs', ['tenant_id', 'prochaine_echeance', 'actif'], 'idx_preventifs_echeance');

        $this->addIndexSafe('depenses', ['tenant_id', 'mois', 'annee', 'statut'], 'idx_depenses_periode');
        $this->addIndexSafe('depenses', ['tenant_id', 'categorie', 'annee'], 'idx_depenses_categorie');

        $this->addIndexSafe('menus_cantine', ['tenant_id', 'date_repas'], 'idx_menus_tenant_date');
        $this->addIndexSafe('repas_journaliers', ['tenant_id', 'date_repas', 'present'], 'idx_repas_date_present');
        $this->addIndexSafe('inscriptions_cantine', ['eleve_id', 'actif'], 'idx_cantine_eleve_actif');
    }

    public function down(): void
    {
        $indexes = [
            'idx_eleves_tenant_statut', 'idx_eleves_tenant_niveau', 'idx_eleves_tenant_created',
            'idx_presences_eleve_statut', 'idx_presences_seance_eleve',
            'idx_factures_tenant_statut', 'idx_factures_tenant_echeance', 'idx_factures_eleve_statut', 'idx_factures_periode',
            'idx_paiements_tenant_statut', 'idx_paiements_tenant_date', 'idx_paiements_facture_statut',
            'idx_absences_tenant_date', 'idx_absences_eleve_date', 'idx_absences_tenant_statut_date',
            'idx_seances_date_statut', 'idx_seances_cours_date',
            'idx_inscriptions_groupe_statut', 'idx_inscriptions_eleve_statut',
            'idx_notes_eleve_eval',
            'idx_transport_circuit_actif', 'idx_transport_eleve_actif', 'idx_pointage_bus_circuit_date',
            'idx_stock_tenant_actif_cat', 'idx_mvt_article_date',
            'idx_personnel_tenant_statut', 'idx_pointage_personnel_date',
            'idx_interventions_statut', 'idx_preventifs_echeance',
            'idx_depenses_periode', 'idx_depenses_categorie',
            'idx_menus_tenant_date', 'idx_repas_date_present', 'idx_cantine_eleve_actif',
        ];

        foreach ($indexes as $idx) {
            DB::statement("DROP INDEX IF EXISTS {$idx}");
        }
    }

    private function addIndexSafe(string $table, array $columns, string $name): void
    {
        $exists = DB::select("
            SELECT 1 FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ", [$table, $name]);

        if (empty($exists)) {
            try {
                Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                    $t->index($columns, $name);
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Index {$name} skipped: " . $e->getMessage());
            }
        }
    }
};
