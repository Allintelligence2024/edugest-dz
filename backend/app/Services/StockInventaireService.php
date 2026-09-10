<?php

namespace App\Services;

use App\Models\ArticleStock;
use App\Models\MouvementStock;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockInventaireService
{
    /**
     * @return array{paginator: LengthAwarePaginator, stats: array{total_articles: int, articles_en_alerte: int, valeur_totale_stock: mixed, par_categorie: mixed}}
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

        $query = ArticleStock::where('actif', true);

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

        return ['paginator' => $paginator, 'stats' => $stats];
    }

    /**
     * @return array{article: ArticleStock, qr_code: mixed, reference: mixed, categorie_label: mixed}
     */
    public function store(array $data): array
    {
        $validated = Validator::make($data, [
            'nom'             => 'required|string|max:150',
            'categorie'       => 'required|in:mobilier,equipement_pedagogique,fourniture_bureau,fourniture_pedagogique,equipement_sportif,materiel_entretien,equipement_informatique,autre',
            'unite'           => 'nullable|string|max:20',
            'quantite_stock'  => 'required|integer|min:0',
            'quantite_minimum'=> 'nullable|integer|min:0',
            'etat'            => 'nullable|in:bon,use,hors_service,en_reparation',
            'valeur_unitaire' => 'nullable|numeric|min:0',
            'date_acquisition'=> 'nullable|date',
            'fournisseur'     => 'nullable|string|max:150',
            'numero_serie'    => 'nullable|string|max:100',
            'salle_id'        => 'nullable|uuid',
            'localisation'    => 'nullable|string|max:100',
            'est_immobilise'  => 'nullable|boolean',
            'note'            => 'nullable|string|max:500',
        ])->validate();

        $article = DB::transaction(function () use ($validated): ArticleStock {
            $art = ArticleStock::create($validated);

            if ($art->quantite_stock > 0) {
                MouvementStock::create([
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
            'article'         => $article,
            'qr_code'         => $article->qr_code,
            'reference'       => $article->reference,
            'categorie_label' => $article->categorie_label,
        ];
    }

    /**
     * @return array{article: ArticleStock, etat_label: mixed, categorie_label: mixed, en_alerte: mixed, valeur_totale: mixed, nb_prets_cours: int}
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
     * @return array{article: ArticleStock}
     */
    public function update(string $id, array $data): array
    {
        $article   = ArticleStock::findOrFail($id);
        $validated = Validator::make($data, [
            'nom'             => 'sometimes|string|max:150',
            'etat'            => 'sometimes|in:bon,use,hors_service,en_reparation',
            'localisation'    => 'nullable|string|max:100',
            'valeur_unitaire' => 'nullable|numeric|min:0',
            'quantite_minimum'=> 'sometimes|integer|min:0',
            'fournisseur'     => 'nullable|string|max:150',
            'est_immobilise'  => 'sometimes|boolean',
            'note'            => 'nullable|string|max:500',
        ])->validate();

        $article->update($validated);

        return ['article' => $article->fresh()];
    }

    /**
     * @return array{success: string}|array{error: string, code: string, status: int}
     */
    public function destroy(string $id): array
    {
        $article = ArticleStock::findOrFail($id);
        if ($article->pretsEnCours()->exists()) {
            return [
                'error'  => 'Impossible de supprimer : des prêts sont en cours',
                'code'   => 'HAS_PRETS',
                'status' => 422,
            ];
        }
        $nom = $article->nom;
        $article->delete();

        return ['success' => "Article '{$nom}' supprimé"];
    }

    /**
     * @return array{article: ArticleStock, etat_label: mixed, categorie_label: mixed, en_alerte: mixed}
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
     * @return array{article: ArticleStock, quantite_avant: mixed, quantite_apres: mixed, en_alerte: bool, article_nom: mixed, article_unite: mixed}|array{error: string, code: string, status: int}
     */
    public function mouvement(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'type'          => 'required|in:entree,sortie,ajustement,transfert,perte',
            'quantite'      => 'required|integer|min:1',
            'motif'         => 'nullable|string|max:200',
            'reference_doc' => 'nullable|string|max:100',
            'date_mouvement'=> 'nullable|date',
        ])->validate();

        $article = ArticleStock::findOrFail($id);
        $avant   = $article->quantite_stock;

        $apres = match ($validated['type']) {
            'entree'    => $avant + $validated['quantite'],
            'sortie',
            'perte'     => $avant - $validated['quantite'],
            'ajustement'=> $validated['quantite'],
            'transfert' => $avant - $validated['quantite'],
            default     => $avant,
        };

        if ($apres < 0) {
            return [
                'error'  => "Stock insuffisant : {$avant} {$article->unite} disponible(s)",
                'code'   => 'STOCK_INSUFFISANT',
                'status' => 422,
            ];
        }

        DB::transaction(function () use ($article, $validated, $avant, $apres) {
            $article->update(['quantite_stock' => $apres]);

            MouvementStock::create([
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
            'article_nom'   => $article->nom,
            'article_unite' => $article->unite,
        ];
    }

    /**
     * @return array{paginator: LengthAwarePaginator, article: array{id: mixed, nom: mixed, stock_actuel: mixed}}
     */
    public function historique(Request $request, string $id): array
    {
        $article   = ArticleStock::findOrFail($id);
        $paginator = MouvementStock::where('article_id', $article->id)
            ->orderByDesc('date_mouvement')
            ->paginate($request->per_page ?? 20);

        return [
            'paginator' => $paginator,
            'article'   => [
                'id'           => $article->id,
                'nom'          => $article->nom,
                'stock_actuel' => $article->quantite_stock,
            ],
        ];
    }

    /**
     * @return array{articles: mixed, nb_alertes: int, message: string}
     */
    public function alertes(): array
    {
        $articles = ArticleStock::where('actif', true)
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
            'message'    => "{$articles->count()} article(s) sous le seuil minimum",
        ];
    }
}
