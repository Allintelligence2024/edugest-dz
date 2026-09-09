<?php

namespace App\Services;

use App\Models\ArticleStock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service de gestion de l'inventaire scolaire.
 * Agrégats GROUP BY via DB::table() (lignes stdClass → aucune propriété modèle exigée),
 * helper privé `table()` reproduisant le scope tenant fail-closed de BelongsToTenant.
 */
class StockInventaireService
{
    public const TABLE_ARTICLE_STOCK = 'article_stock';

    /**
     * @var Builder
     */
    private $builder;

/**
     * Builder DB::table() répliquant le scope tenant (fail-closed).
     * Sans contexte tenant → whereRaw('1 = 0') (aucun résultat).
     */
    protected function table(string $table): Builder
    {
        $builder = DB::table($table);
        $tenantId = config('tenant.current_id');

        if ($tenantId !== null) {
            $builder->where('tenant_id', $tenantId);
        } else {
            $builder->whereRaw('1 = 0');
        }

        return $builder;
    }

        return $builder;
    }

    /**
     * Liste paginée des articles avec filtres facultatifs.
     */
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'search'       => 'nullable|string|max:100',
            'categorie'    => 'nullable|string',
            'etat'         => 'nullable|in:bon,use,hors_service,en_reparation',
            'en_alerte'    => 'nullable|boolean',
            'immobilise'   => 'nullable|boolean',
            'per_page'     => 'nullable|integer|min:5|max:100',
        ]);

        $query = $this->table('article_stock')->where('actif', true);

        if (!empty($validated['search'])) {
            $query->search($validated['search']);
        }
        if (!empty($validated['categorie'])) {
            $query->categorie($validated['categorie']);
        }
        if (!empty($validated['etat'])) {
            $query->where('etat', $validated['etat']);
        }
        if (isset($validated['en_alerte']) && $validated['en_alerte']) {
            $query->enAlerte();
        }
        if (isset($validated['immobilise'])) {
            $query->where('est_immobilise', $validated['immobilise']);
        }

        $paginator = $query->orderBy('categorie')->orderBy('nom')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_articles'    => ArticleStock::where('actif', true)->count(),
            'articles_en_alerte'=> ArticleStock::where('actif', true)->enAlerte()->count(),
            'valeur_totale_stock'=> ArticleStock::where('actif', true)
                ->selectRaw('SUM(quantite_stock * COALESCE(valeur_unitaire, 0)) as total')
                ->value('total') ?? 0,
            'par_categorie'     => ArticleStock::where('actif', true)
                ->selectRaw('categorie, COUNT(*) as nb, SUM(quantite_stock) as total_qte')
                ->groupBy('categorie')
                ->get(),
        ];

        return [
            'paginator' => $paginator,
            'stats'     => $stats,
        ];
    }

    /**
     * Récupération d'un article avec ses relations.
     */
    public function show(string $id): array
    {
        $article = ArticleStock::with([
            'mouvements' => fn($q) => $q->orderByDesc('date_mouvement')->limit(20),
            'pretsEnCours',
        ])->findOrFail($id);

        return [
            'article'        => $article,
            'etat_label'     => $article->etat_label,
            'categorie_label'=> $article->categorie_label,
            'en_alerte'      => $article->en_alerte,
            'valeur_totale'  => $article->valeur_totale,
            'nb_prets_cours' => $article->pretsEnCours->count(),
        ];
    }

    /**
     * Création d'un article avec journalisation du mouvement de stock.
     */
    public function store(array $validated): array
    {
        $article = DB::transaction(function () use ($validated) {
            $art = ArticleStock::create($validated);

            if ($art->quantite_stock > 0) {
                $this->table('mouvements_stocks')->create([
                    'tenant_id'      => config('tenant.current_id'),
                    'article_id'     => $art->id,
                    'type'           => 'entree',
                    'quantite'       => $art->quantite_stock,
                    'quantite_avant' => 0,
                    'quantite_apres' => $art->quantite_stock,
                    'motif'          => 'Stock initial',
                    'saisie_par'     => Auth::id(),
                    'date_mouvement' => today(),
                ]);
            }

            return $art;
        });

        return [
            'article'          => $article,
            'qr_code'          => $article->qr_code,
            'reference'        => $article->reference,
            'categorie_label'  => $article->categorie_label,
        ];
    }

    /**
     * Mise à jour d'un article.
     */
    public function update(string $id, array $validated): array
    {
        $article = ArticleStock::findOrFail($id);
        $article->update($validated);

        return [
            'article'        => $article->fresh(),
            'etat_label'     => $article->etat_label,
            'categorie_label'=> $article->categorie_label,
            'en_alerte'      => $article->en_alerte,
            'valeur_totale'  => $article->valeur_totale,
            'nb_prets_cours' => $article->pretsEnCours->count(),
        ];
    }

    /**
     * Suppression d'un article (garde-fou : interdit s'il des prêts en cours).
     */
    public function destroy(string $id): array
    {
        $article = ArticleStock::findOrFail($id);
        if ($article->pretsEnCours()->exists()) {
            return ['error' => 'Impossible de supprimer : des prêts sont en cours', 'code' => 'HAS_PRETS', 'status' => 422];
        }
        $nom = $article->nom;
        $article->delete();
        return ['success' => "Article '{$nom}' supprimé"];
    }

    /**
     * Récupération d'un article par QR code.
     */
    public function parQrCode(string $qrCode): array
    {
        $article = ArticleStock::where('qr_code', $qrCode)->firstOrFail();

        return [
            'article'        => $article,
            'etat_label'     => $article->etat_label,
            'categorie_label'=> $article->categorie_label,
            'en_alerte'      => $article->en_alerte,
        ];
    }

    /**
     * Enregistrement d'un mouvement de stock (entrée / sortie / ajustement).
     */
    public function mouvement(string $id, array $validated): array
    {
        $article = ArticleStock::findOrFail($id);
        $avant   = $article->quantite_stock;

        $apres = match ($validated['type']) {
            'entree'    => $avant + $validated['quantite'],
            'sortie'            => max(0, $avant - $validated['quantite']),
            'perte'           => max(0, $avant - $validated['quantite']),
            'ajustement'    => $validated['quantite'],
            'transfert'     => max(0, $avant - $validated['quantite']),
            default         => $avant,
        };

        if ($apres < 0) {
            return ['error' => "Stock insuffisant : {$avant} unité(s) disponible(s)", 'code' => 'STOCK_INSUFFISANT', 'status' => 422];
        }

        DB::transaction(function () use ($article, $validated, $avant, $apres) {
            $article->update(['quantite_stock' => $apres]);

            $this->table('mouvements_stocks')->create([
                'tenant_id'      => config('tenant.current_id'),
                'article_id'     => $article->id,
                'type'           => $validated['type'],
                'quantite'       => $validated['quantite'],
                'quantite_avant' => $avant,
                'quantite_apres' => $apres,
                'motif'          => $validated['motif'] ?? null,
                'reference_doc'  => $validated['reference_doc'] ?? null,
                'saisie_par'     => Auth::id(),
                'date_mouvement' => $validated['date_mouvement'] ?? today(),
            ]);
        });

        return [
            'article'       => $article->fresh(),
            'quantite_avant'=> $avant,
            'quantite_apres'=> $apres,
            'en_alerte'     => $apres <= $article->quantite_minimum,
        ];
    }

    /**
     * Historique des mouvements d'un article.
     */
    public function historique(string $id, Request $request): array
    {
        $article = ArticleStock::findOrFail($id);
        $paginator = $this->table('mouvements_stocks')
            ->where('article_id', $article->id)
            ->orderByDesc('date_mouvement')
            ->paginate($request->per_page ?? 20);

        return [
            'paginator' => $paginator,
            'article'   => ['id' => $article->id, 'nom' => $article->nom, 'stock_actuel' => $article->quantite_stock],
        ];
    }

    /**
     * Liste des articles en alerte (stock sous seuil).
     */
    public function alertes(): array
    {
        $articles = $this->table('article_stock')
            ->where('actif', true)
            ->enAlerte()
            ->orderBy('quantite_stock')
            ->get()
            ->map(fn($a) => [
                'article'         => $a,
                'categorie_label' => $a->categorie_label,
                'deficit'         => max(0, $a->quantite_minimum - $a->quantite_stock),
                'en_alerte'       => true,
            ]);

        return [
            'articles'   => $articles,
            'nb_alertes' => $articles->count(),
        ];
    }
}