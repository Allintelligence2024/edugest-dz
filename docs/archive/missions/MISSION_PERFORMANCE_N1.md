# 🤖 MISSION DEEPSEEK — Performance : Éliminer les requêtes N+1
## EduGest DZ · Branche : develop · 1er Juillet 2026
## Tests actuels : 313 ✅ · Objectif : 313 ✅ (0 régression) + temps de réponse < 200ms

---

## CONTEXTE — Le problème N+1

Une requête N+1 se produit quand on charge N enregistrements, puis
pour chaque enregistrement on fait 1 requête supplémentaire.
Avec 200 élèves, ça fait 201 requêtes au lieu de 2.

**Symptômes :** page qui met 3-8 secondes à charger.
**Solution :** `with()` eager loading + index PostgreSQL.

### Ce qui EXISTE (à analyser et corriger)
- `EleveController` → relations `with()` partielles
- `FinanceController` → boucle de 12 mois sans eager loading
- `TransportController` → `nb_eleves_actifs` chargé en boucle
- `StockInventaireController` → `par_categorie` = N requêtes groupBy
- Aucun index composite sur `tenant_id + colonnes fréquentes`

### IMPORTANT — Règles pour cette mission
1. **Aucun test ne doit casser** — vérifier après chaque modification
2. **Ne pas modifier les signatures d'API** — seul le code interne change
3. **Mesurer avant et après** — utiliser `DB::listen()` pour compter les requêtes
4. **Ajouter des index via migrations** — ne jamais modifier le schéma directement

---

## PARTIE 1 — Index PostgreSQL manquants

### ÉTAPE 1 — Migration index de performance

**Créer :** `edugestdz/backend/database/migrations/2026_07_01_300000_add_performance_indexes.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Index composites critiques (tenant_id + colonne fréquente) ──

        // Élèves — recherche par statut dans un tenant
        $this->addIndexSafe('eleves', ['tenant_id', 'statut'], 'idx_eleves_tenant_statut');
        $this->addIndexSafe('eleves', ['tenant_id', 'niveau_scolaire'], 'idx_eleves_tenant_niveau');
        $this->addIndexSafe('eleves', ['tenant_id', 'created_at'], 'idx_eleves_tenant_created');

        // Présences — requêtes fréquentes
        $this->addIndexSafe('presences', ['eleve_id', 'statut'], 'idx_presences_eleve_statut');
        $this->addIndexSafe('presences', ['seance_id', 'eleve_id'], 'idx_presences_seance_eleve');

        // Factures — finance dashboard
        $this->addIndexSafe('factures', ['tenant_id', 'statut'], 'idx_factures_tenant_statut');
        $this->addIndexSafe('factures', ['tenant_id', 'date_echeance'], 'idx_factures_tenant_echeance');
        $this->addIndexSafe('factures', ['eleve_id', 'statut'], 'idx_factures_eleve_statut');
        $this->addIndexSafe('factures', ['mois', 'annee', 'tenant_id'], 'idx_factures_periode');

        // Paiements — calculs CA
        $this->addIndexSafe('paiements', ['tenant_id', 'statut'], 'idx_paiements_tenant_statut');
        $this->addIndexSafe('paiements', ['tenant_id', 'date_paiement'], 'idx_paiements_tenant_date');
        $this->addIndexSafe('paiements', ['facture_id', 'statut'], 'idx_paiements_facture_statut');

        // Absences journalières
        $this->addIndexSafe('absences_journalieres', ['tenant_id', 'date_absence'], 'idx_absences_tenant_date');
        $this->addIndexSafe('absences_journalieres', ['eleve_id', 'date_absence'], 'idx_absences_eleve_date');
        $this->addIndexSafe('absences_journalieres', ['tenant_id', 'statut', 'date_absence'], 'idx_absences_tenant_statut_date');

        // Séances — planning
        $this->addIndexSafe('seances', ['date_seance', 'statut'], 'idx_seances_date_statut');
        $this->addIndexSafe('seances', ['cours_id', 'date_seance'], 'idx_seances_cours_date');

        // Inscriptions — groupes
        $this->addIndexSafe('inscriptions', ['groupe_id', 'statut'], 'idx_inscriptions_groupe_statut');
        $this->addIndexSafe('inscriptions', ['eleve_id', 'statut'], 'idx_inscriptions_eleve_statut');

        // Notes — calcul moyennes
        $this->addIndexSafe('notes', ['eleve_id', 'evaluation_id'], 'idx_notes_eleve_eval');

        // Transport
        $this->addIndexSafe('transport_eleves', ['circuit_id', 'actif'], 'idx_transport_circuit_actif');
        $this->addIndexSafe('transport_eleves', ['eleve_id', 'actif'], 'idx_transport_eleve_actif');
        $this->addIndexSafe('pointage_bus', ['circuit_id', 'date', 'trajet'], 'idx_pointage_bus_circuit_date');

        // Stock
        $this->addIndexSafe('articles_stock', ['tenant_id', 'actif', 'categorie'], 'idx_stock_tenant_actif_cat');
        $this->addIndexSafe('mouvements_stock', ['article_id', 'date_mouvement'], 'idx_mvt_article_date');

        // Personnel
        $this->addIndexSafe('personnel_non_enseignant', ['tenant_id', 'statut', 'poste'], 'idx_personnel_tenant_statut');
        $this->addIndexSafe('pointage_personnel', ['agent_id', 'date'], 'idx_pointage_personnel_date');

        // Entretien
        $this->addIndexSafe('interventions_entretien', ['tenant_id', 'statut', 'priorite'], 'idx_interventions_statut');
        $this->addIndexSafe('entretiens_preventifs', ['tenant_id', 'prochaine_echeance', 'actif'], 'idx_preventifs_echeance');

        // Budget
        $this->addIndexSafe('depenses', ['tenant_id', 'mois', 'annee', 'statut'], 'idx_depenses_periode');
        $this->addIndexSafe('depenses', ['tenant_id', 'categorie', 'annee'], 'idx_depenses_categorie');

        // Cantine
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

    /**
     * Ajoute un index seulement s'il n'existe pas déjà.
     * Évite les erreurs si la migration est rejouée.
     */
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
                // Table ou colonne n'existe pas encore — ignorer
                \Illuminate\Support\Facades\Log::warning("Index {$name} skipped: " . $e->getMessage());
            }
        }
    }
};
```

---

## PARTIE 2 — Corriger les N+1 dans les Controllers

### ÉTAPE 2 — EleveController : optimiser index() et show()

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/EleveController.php`

**Remplacer la méthode `index()`** :

```php
public function index(Request $request): JsonResponse
{
    $eleves = $this->indexQuery($request)
        ->with([
            'wilaya:id,nom_fr',
            'commune:id,nom_fr',
            // Charger les inscriptions actives en une seule requête
            'inscriptions' => fn($q) => $q->where('statut', 'validée')
                ->with('groupe:id,nom,matiere_id')
                ->select('id', 'eleve_id', 'groupe_id', 'statut'),
        ])
        ->withCount([
            'inscriptions as nb_inscriptions' => fn($q) => $q->where('statut', 'validée'),
            'presences as nb_presences',
            'presences as nb_absences' => fn($q) => $q->where('statut', 'absent'),
        ])
        ->paginate($request->per_page ?? $this->perPage);

    // Stats avec cache 5 minutes — évite de recalculer à chaque requête de liste
    $stats = cache()->remember(
        "eleves_stats_" . config('tenant.current_id'),
        300,
        fn() => [
            'total'    => \App\Models\Eleve::count(),
            'actifs'   => \App\Models\Eleve::where('statut', 'actif')->count(),
            'nouveaux' => \App\Models\Eleve::whereMonth('created_at', now()->month)->count(),
        ]
    );

    return $this->paginatedResponse($eleves, 'Élèves récupérés', ['stats' => $stats]);
}
```

**Remplacer la méthode `show()`** :

```php
public function show(string $id): JsonResponse
{
    // Charger TOUT en une seule requête avec les bonnes relations
    $eleve = \App\Models\Eleve::with([
        'wilaya:id,nom_fr,nom_ar',
        'commune:id,nom_fr',
        'parents:id,nom,prenom,telephone_1,telephone_2,email',
        'inscriptions' => fn($q) => $q
            ->where('statut', 'validée')
            ->with('groupe:id,nom,matiere_id,enseignant_id')
            ->with('groupe.matiere:id,nom_fr,coefficient')
            ->with('groupe.enseignant:id,nom,prenom'),
    ])
    ->withCount([
        'presences',
        'presences as presences_presentes' => fn($q) => $q->whereIn('statut', ['présent', 'retard']),
        'factures as factures_impayees'    => fn($q) => $q->whereNotIn('statut', ['payée', 'annulée']),
    ])
    ->findOrFail($id);

    // Stats académiques calculées en une seule fois
    $stats = $this->eleveService->getStatsAcademiques($eleve);

    return $this->success([
        'eleve'       => $eleve,
        'statistiques'=> $stats,
    ]);
}
```

---

### ÉTAPE 3 — FinanceController : optimiser getTableauBord()

**Modifier :** `edugestdz/backend/app/Services/FacturationService.php`

**Remplacer la méthode `getTableauBord()`** dans `FacturationService.php` :

```php
public function getTableauBord(): array
{
    $moisActuel = now()->month;
    $annee      = now()->year;
    $tenantId   = config('tenant.current_id');

    // ── UNE seule requête pour CA mois + CA année ──
    $paiementsStats = \App\Models\Paiement::where('statut', 'confirmé')
        ->whereYear('date_paiement', $annee)
        ->selectRaw("
            SUM(montant) as ca_annee,
            SUM(CASE WHEN EXTRACT(MONTH FROM date_paiement) = ? THEN montant ELSE 0 END) as ca_mois
        ", [$moisActuel])
        ->first();

    // ── UNE seule requête pour impayés ──
    $impayesStats = \App\Models\Facture::whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
        ->selectRaw("
            SUM(total_ttc) as total_impayes,
            COUNT(CASE WHEN date_echeance < CURRENT_DATE THEN 1 END) as nb_impayes
        ")
        ->first();

    // ── Évolution 6 mois en UNE seule requête ──
    $caParMois = \App\Models\Paiement::where('statut', 'confirmé')
        ->where('date_paiement', '>=', now()->subMonths(5)->startOfMonth())
        ->selectRaw("
            DATE_TRUNC('month', date_paiement) as mois_date,
            SUM(montant) as total
        ")
        ->groupByRaw("DATE_TRUNC('month', date_paiement)")
        ->orderBy('mois_date')
        ->get()
        ->map(fn($r) => [
            'mois'  => \Carbon\Carbon::parse($r->mois_date)->translatedFormat('M Y'),
            'total' => (float) $r->total,
        ]);

    // ── Modes de paiement du mois en UNE requête ──
    $modesPayment = \App\Models\Paiement::where('statut', 'confirmé')
        ->whereMonth('date_paiement', $moisActuel)
        ->whereYear('date_paiement', $annee)
        ->selectRaw('mode_paiement, SUM(montant) as total')
        ->groupBy('mode_paiement')
        ->pluck('total', 'mode_paiement');

    return [
        'ca_mois'      => (float) ($paiementsStats->ca_mois ?? 0),
        'ca_annee'     => (float) ($paiementsStats->ca_annee ?? 0),
        'impayes'      => (float) ($impayesStats->total_impayes ?? 0),
        'nb_impayes'   => (int)   ($impayesStats->nb_impayes ?? 0),
        'ca_par_mois'  => $caParMois,
        'modes_payment'=> $modesPayment,
    ];
}
```

---

### ÉTAPE 4 — BulletinService : optimiser genererBulletins()

**Modifier :** `edugestdz/backend/app/Services/BulletinService.php`

**Remplacer la méthode `genererBulletins()`** — le problème : elle charge les notes élève par élève en boucle.

```php
public function genererBulletins(string $groupeId, string $trimestre, string $anneeScolaire): array
{
    $groupe = \App\Models\Groupe::findOrFail($groupeId);

    // ── Charger TOUS les élèves du groupe en une requête ──
    $eleves = \App\Models\Eleve::whereHas('inscriptions', fn($q) =>
        $q->where('groupe_id', $groupeId)->where('statut', 'validée')
    )->get();

    $effectif = $eleves->count();

    // ── Charger TOUTES les notes du groupe en UNE seule requête ──
    $toutesLesNotes = \App\Models\Note::whereHas('evaluation', fn($q) =>
        $q->where('groupe_id', $groupeId)->where('trimestre', $trimestre)
    )
    ->whereIn('eleve_id', $eleves->pluck('id'))
    ->whereNotNull('note')
    ->with('evaluation:id,coefficient,groupe_id,trimestre')
    ->get()
    ->groupBy('eleve_id'); // grouper en mémoire, pas en BDD

    // ── Charger TOUTES les présences du groupe en UNE requête ──
    $toutesLesPresences = \App\Models\Presence::whereHas('seance.cours', fn($q) =>
        $q->where('groupe_id', $groupeId)
    )
    ->whereIn('eleve_id', $eleves->pluck('id'))
    ->selectRaw('eleve_id, statut, COUNT(*) as total')
    ->groupBy('eleve_id', 'statut')
    ->get()
    ->groupBy('eleve_id');

    // ── Calculer les moyennes EN MÉMOIRE (pas de requêtes BDD en boucle) ──
    $moyennes = $eleves->map(function ($eleve) use ($toutesLesNotes) {
        $notesEleve = $toutesLesNotes->get($eleve->id, collect());

        if ($notesEleve->isEmpty()) {
            return ['eleve_id' => $eleve->id, 'moyenne' => 0.0];
        }

        $totalPondere = $notesEleve->sum(fn($n) => $n->note * $n->evaluation->coefficient);
        $totalCoeff   = $notesEleve->sum(fn($n) => $n->evaluation->coefficient);
        $moyenne      = $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : 0.0;

        return ['eleve_id' => $eleve->id, 'moyenne' => $moyenne];
    })->sortByDesc('moyenne');

    // ── Attribuer les rangs ──
    $rang = 1;
    $moyennesAvecRang = $moyennes->map(fn($item) => [...$item, 'rang' => $rang++])->keyBy('eleve_id');

    // ── Générer les bulletins en transaction unique ──
    $bulletinsGeneres = [];
    \Illuminate\Support\Facades\DB::transaction(function () use (
        $eleves, $groupe, $trimestre, $anneeScolaire,
        $effectif, $moyennesAvecRang, $toutesLesPresences, &$bulletinsGeneres
    ) {
        foreach ($eleves as $eleve) {
            $data = $moyennesAvecRang[$eleve->id] ?? ['moyenne' => 0, 'rang' => $effectif];

            // Stats présences depuis la collection en mémoire
            $presenceEleve = $toutesLesPresences->get($eleve->id, collect());
            $nbPresent     = $presenceEleve->where('statut', 'présent')->sum('total');
            $nbAbsent      = $presenceEleve->where('statut', 'absent')->sum('total');

            $bulletin = \App\Models\Bulletin::updateOrCreate(
                [
                    'eleve_id'      => $eleve->id,
                    'groupe_id'     => $groupe->id,
                    'trimestre'     => $trimestre,
                    'annee_scolaire'=> $anneeScolaire,
                ],
                [
                    'tenant_id'       => config('tenant.current_id'),
                    'moyenne_generale'=> $data['moyenne'],
                    'rang'            => $data['rang'],
                    'effectif_classe' => $effectif,
                    'appreciation_gen'=> $this->getAppreciation($data['moyenne']),
                    'genere_le'       => now(),
                    'genere_par'      => auth('api')->id(),
                ]
            );

            $pdfPath = $this->genererPDF($bulletin->fresh()->load('eleve', 'groupe.matiere'));
            $bulletin->update(['fichier_url' => $pdfPath]);

            $bulletinsGeneres[] = [
                'bulletin_id' => $bulletin->id,
                'eleve'       => $eleve->nom_complet,
                'moyenne'     => $data['moyenne'],
                'rang'        => $data['rang'],
            ];
        }
    });

    return $bulletinsGeneres;
}
```

---

### ÉTAPE 5 — TransportController : optimiser indexCircuits()

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/TransportController.php`

**Remplacer la méthode `indexCircuits()`** :

```php
public function indexCircuits(Request $request): JsonResponse
{
    // ── Charger TOUT en une requête avec withCount ──
    $circuits = CircuitTransport::with([
        'chauffeur:id,nom,prenom,telephone',
        'arrets:id,circuit_id,nom,ordre,heure_matin,heure_soir',
    ])
    ->withCount([
        // Compte les inscriptions actives sans N+1
        'inscriptionsActives as nb_eleves_actifs',
    ])
    ->when($request->filled('actif'), fn($q) => $q->where('actif', (bool) $request->actif))
    ->orderBy('nom')
    ->get()
    ->map(fn($c) => [
        ...$c->toArray(),
        // Utiliser le withCount au lieu de la propriété calculée (qui fait une requête)
        'nb_eleves'        => $c->nb_eleves_actifs,
        'taux_remplissage' => $c->capacite > 0
            ? round(($c->nb_eleves_actifs / $c->capacite) * 100, 1)
            : 0,
        'alertes'          => $c->alertes_maintenance,
    ]);

    return $this->success([
        'circuits' => $circuits,
        'stats'    => [
            'total'       => $circuits->count(),
            'actifs'      => $circuits->where('actif', true)->count(),
            'total_eleves'=> $circuits->sum('nb_eleves'),
        ],
    ], 'Circuits récupérés');
}
```

---

### ÉTAPE 6 — BudgetController : optimiser previsionnel()

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/BudgetController.php`

**Remplacer la méthode `previsionnel()`** :

```php
public function previsionnel(Request $request): JsonResponse
{
    $annee = (int) ($request->annee ?? now()->year);
    $mois  = $request->filled('mois') ? (int) $request->mois : null;

    $categories = [
        'salaires_enseignants', 'salaires_personnel', 'loyer',
        'electricite_gaz', 'eau', 'telephone_internet',
        'fournitures_bureau', 'fournitures_pedagogiques',
        'maintenance_reparation', 'assurance', 'publicite_marketing',
        'transport', 'cantine_restauration', 'taxes_impots', 'autres',
    ];

    // ── UNE requête pour toutes les prévisions ──
    $previsions = \App\Models\BudgetPrevisionnel::where('annee', $annee)
        ->where('mois', $mois)
        ->get()
        ->keyBy('categorie');

    // ── UNE requête pour tous les réalisés ──
    $realises = \App\Models\Depense::validees()
        ->where('annee', $annee)
        ->when($mois, fn($q) => $q->where('mois', $mois))
        ->selectRaw('categorie, SUM(montant) as total_realise')
        ->groupBy('categorie')
        ->get()
        ->keyBy('categorie');

    // ── Construire le résultat EN MÉMOIRE ──
    $data = collect($categories)->map(function (string $cat) use ($previsions, $realises) {
        $prevu   = (float) ($previsions[$cat]?->montant_prevu ?? 0);
        $realise = (float) ($realises[$cat]?->total_realise   ?? 0);

        return [
            'categorie'   => $cat,
            'libelle'     => \App\Models\Depense::categorieLibelle($cat),
            'prevu'       => $prevu,
            'realise'     => $realise,
            'ecart'       => $prevu - $realise,
            'pct_realise' => $prevu > 0 ? round(($realise / $prevu) * 100, 1) : null,
        ];
    });

    return $this->success([
        'annee'         => $annee,
        'mois'          => $mois,
        'lignes'        => $data,
        'total_prevu'   => $data->sum('prevu'),
        'total_realise' => $data->sum('realise'),
        'ecart_total'   => $data->sum('ecart'),
    ], "Budget prévisionnel {$annee}");
}
```

---

### ÉTAPE 7 — Ajouter cache Redis sur les endpoints lents

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/BudgetController.php`

**Remplacer la méthode `dashboard()`** :

```php
public function dashboard(Request $request): JsonResponse
{
    $mois  = (int) ($request->mois  ?? now()->month);
    $annee = (int) ($request->annee ?? now()->year);
    $key   = "budget_dashboard_" . config('tenant.current_id') . "_{$mois}_{$annee}";

    // Cache 10 minutes — le dashboard finance ne change pas seconde par seconde
    $data = cache()->remember($key, 600, function () use ($mois, $annee) {
        // Recettes : UNE requête
        $recettes = (float) \App\Models\Paiement::where('statut', 'confirmé')
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');

        // Dépenses : UNE requête
        $depenses = (float) \App\Models\Depense::validees()
            ->periode($mois, $annee)
            ->sum('montant');

        // Impayés : UNE requête
        $impayes = (float) \App\Models\Facture::whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
            ->where('date_echeance', '<', today())
            ->sum('total_ttc');

        // Par catégorie : UNE requête
        $parCategorie = \App\Models\Depense::validees()
            ->periode($mois, $annee)
            ->selectRaw('categorie, SUM(montant) as total')
            ->groupBy('categorie')
            ->get()
            ->mapWithKeys(fn($r) => [
                $r->categorie => [
                    'libelle' => \App\Models\Depense::categorieLibelle($r->categorie),
                    'total'   => (float) $r->total,
                    'prevu'   => \App\Models\BudgetPrevisionnel::getPrevision($r->categorie, now()->year, $mois),
                ],
            ]);

        // Évolution 6 mois : UNE requête chacun (2 requêtes)
        $evolution = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $evolution[] = [
                'label'    => $date->translatedFormat('M Y'),
                'recettes' => (float) \App\Models\Paiement::where('statut', 'confirmé')
                    ->whereMonth('date_paiement', $date->month)
                    ->whereYear('date_paiement', $date->year)
                    ->sum('montant'),
                'depenses' => (float) \App\Models\Depense::validees()
                    ->periode($date->month, $date->year)
                    ->sum('montant'),
            ];
            $evolution[count($evolution) - 1]['resultat'] =
                $evolution[count($evolution) - 1]['recettes'] - $evolution[count($evolution) - 1]['depenses'];
        }

        return [
            'recettes'      => $recettes,
            'depenses'      => $depenses,
            'resultat_net'  => $recettes - $depenses,
            'impayes'       => $impayes,
            'par_categorie' => $parCategorie,
            'evolution'     => $evolution,
        ];
    });

    return $this->success(array_merge($data, ['periode' => compact('mois', 'annee')]), "Dashboard budget {$mois}/{$annee}");
}
```

---

### ÉTAPE 8 — Invalider le cache après écriture

**Modifier :** `edugestdz/backend/app/Models/Depense.php`

Ajouter dans les `boot()` ou à la fin des méthodes dans `BudgetController` — ajouter ces lignes après chaque création/modification de dépense :

Dans `BudgetController::storeDepense()`, ajouter après `$depense = Depense::create($validated);` :

```php
// Invalider le cache budget après écriture
cache()->forget("budget_dashboard_" . config('tenant.current_id') . "_{$validated['mois']}_{$validated['annee']}");
```

Dans `BudgetController::destroyDepense()`, ajouter avant `return` :

```php
cache()->forget("budget_dashboard_" . config('tenant.current_id') . "_{$depense->mois}_{$depense->annee}");
```

---

### ÉTAPE 9 — Observer pour invalider le cache élèves

**Créer :** `edugestdz/backend/app/Observers/EleveObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Eleve;
use Illuminate\Support\Facades\Cache;

class EleveObserver
{
    public function created(Eleve $eleve): void
    {
        Cache::forget("eleves_stats_{$eleve->tenant_id}");
    }

    public function updated(Eleve $eleve): void
    {
        Cache::forget("eleves_stats_{$eleve->tenant_id}");
    }

    public function deleted(Eleve $eleve): void
    {
        Cache::forget("eleves_stats_{$eleve->tenant_id}");
    }
}
```

**Modifier :** `edugestdz/backend/app/Providers/AppServiceProvider.php`

Ajouter dans la méthode `boot()` :

```php
\App\Models\Eleve::observe(\App\Observers\EleveObserver::class);
```

---

### ÉTAPE 10 — Middleware de monitoring des requêtes (dev seulement)

**Créer :** `edugestdz/backend/app/Http/Middleware/QueryMonitor.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Middleware de monitoring des requêtes SQL.
 * Active uniquement en développement.
 * Alerte si > 20 requêtes ou > 500ms pour une route.
 */
class QueryMonitor
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production')) {
            return $next($request);
        }

        $queries    = [];
        $startTime  = microtime(true);

        DB::listen(function ($query) use (&$queries) {
            $queries[] = [
                'sql'  => $query->sql,
                'time' => $query->time,
            ];
        });

        $response  = $next($request);
        $totalTime = round((microtime(true) - $startTime) * 1000, 1);
        $nbQueries = count($queries);
        $slowQueries = collect($queries)->filter(fn($q) => $q['time'] > 100);

        // Alerter si trop de requêtes ou trop lent
        if ($nbQueries > 20 || $totalTime > 500) {
            Log::warning('[QueryMonitor] Performance alert', [
                'route'       => $request->path(),
                'nb_queries'  => $nbQueries,
                'total_ms'    => $totalTime,
                'slow_queries'=> $slowQueries->count(),
            ]);
        }

        // Ajouter les métriques dans les headers (visibles dans DevTools)
        $response->headers->set('X-Query-Count', $nbQueries);
        $response->headers->set('X-Response-Time', $totalTime . 'ms');

        return $response;
    }
}
```

**Modifier :** `edugestdz/backend/bootstrap/app.php`

Ajouter le middleware sur les routes API en développement :

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \App\Http\Middleware\QueryMonitor::class,
    ]);
})
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser develop avec main
git checkout develop
git pull origin main

# 1. Migration index de performance
create: edugestdz/backend/database/migrations/2026_07_01_300000_add_performance_indexes.php

# 2. Corriger EleveController
modify: edugestdz/backend/app/Http/Controllers/Api/V1/EleveController.php
# → Remplacer index() et show()

# 3. Corriger FacturationService (getTableauBord)
modify: edugestdz/backend/app/Services/FacturationService.php
# → Remplacer getTableauBord()

# 4. Corriger BulletinService (genererBulletins)
modify: edugestdz/backend/app/Services/BulletinService.php
# → Remplacer genererBulletins()

# 5. Corriger TransportController (indexCircuits)
modify: edugestdz/backend/app/Http/Controllers/Api/V1/TransportController.php
# → Remplacer indexCircuits()

# 6. Corriger BudgetController (previsionnel + dashboard avec cache)
modify: edugestdz/backend/app/Http/Controllers/Api/V1/BudgetController.php
# → Remplacer previsionnel() et dashboard()
# → Ajouter cache()->forget() dans storeDepense() et destroyDepense()

# 7. Créer EleveObserver
create: edugestdz/backend/app/Observers/EleveObserver.php

# 8. Enregistrer l'observer dans AppServiceProvider
modify: edugestdz/backend/app/Providers/AppServiceProvider.php

# 9. Créer QueryMonitor middleware
create: edugestdz/backend/app/Http/Middleware/QueryMonitor.php

# 10. Enregistrer QueryMonitor dans bootstrap/app.php
modify: edugestdz/backend/bootstrap/app.php

# 11. Lancer la migration
php artisan migrate

# 12. Vider les caches Laravel
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 13. Lancer les tests — AUCUNE régression tolérée
php artisan test --parallel
# → Attendu : exactement 313 tests verts (ni plus, ni moins)

# 14. Si tout est vert
git add .
git commit -m "perf: Index PostgreSQL + élimination N+1 + cache Redis — EleveController, FacturationService, BulletinService, TransportController, BudgetController"
git push origin develop

# 15. PR develop → main
```

---

## VÉRIFICATION MANUELLE (optionnelle mais recommandée)

Après le merge, tester les endpoints les plus lourds et vérifier les headers :

```bash
# Tester le dashboard finance
curl -H "Authorization: Bearer <token>" \
     http://localhost/api/v1/finance/tableau-bord \
     -v 2>&1 | grep "X-Query-Count\|X-Response-Time"

# Attendu :
# X-Query-Count: 4   (au lieu de 20+)
# X-Response-Time: 45ms  (au lieu de 500+ms)

# Tester la liste des élèves
curl -H "Authorization: Bearer <token>" \
     "http://localhost/api/v1/eleves?per_page=20" \
     -v 2>&1 | grep "X-Query-Count\|X-Response-Time"

# Attendu :
# X-Query-Count: 3
# X-Response-Time: 35ms
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_PERFORMANCE_N1.md — 15 étapes dans l'ordre.

RÈGLE ABSOLUE : 313 tests doivent passer après cette mission.
Aucune nouvelle fonctionnalité — uniquement de l'optimisation interne.
Ne pas modifier les signatures d'API (mêmes paramètres, mêmes réponses).

Après la migration et les corrections :
  php artisan test --parallel → exactement 313 ✅
  git commit + push + PR → main
```
