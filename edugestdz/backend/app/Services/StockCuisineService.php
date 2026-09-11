<?php

namespace App\Services;

use App\Models\MouvementStockCuisine;
use App\Models\StockCuisine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StockCuisineService
{
    /**
     * @return array{articles: mixed, nb_alertes: int, nb_articles: int}
     */
    public function indexStock(Request $request): array
    {
        $query = StockCuisine::query();

        if ($request->filled('categorie')) {
            $query->where('categorie', $request->categorie);
        }

        $articles = $query->orderBy('article')->get()->map(fn($a) => array_merge(
            $a->toArray(),
            ['en_alerte' => $a->en_alert, 'perime_soon' => $a->perime_soon]
        ));

        return [
            'articles'    => $articles,
            'nb_alertes'  => $articles->where('en_alerte', true)->count(),
            'nb_articles' => $articles->count(),
        ];
    }

    /**
     * @return array{article: StockCuisine, message: string}
     */
    public function storeStock(array $data): array
    {
        $validated = Validator::make($data, [
            'article'         => 'required|string|max:150',
            'categorie'       => 'required|in:legumes,viandes,poissons,produits_laitiers,cereales,condiments,boissons,autres',
            'unite'           => 'required|string|max:20',
            'quantite_stock'  => 'required|numeric|min:0',
            'seuil_alerte'    => 'required|numeric|min:0',
            'prix_unitaire'   => 'nullable|numeric|min:0',
            'fournisseur'     => 'nullable|string|max:150',
            'date_peremption' => 'nullable|date|after:today',
        ])->validate();

        $article = StockCuisine::create($validated);

        return [
            'article' => $article,
            'message' => "Article '{$article->article}' ajoute au stock",
        ];
    }

    /**
     * @return array{article: StockCuisine, mouvement: mixed, quantite: mixed, nouveau_stock: mixed, en_alerte: bool, article_nom: mixed, article_unite: mixed}|array{error: string, code: string, status: int}
     */
    public function mouvementStock(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'type'           => 'required|in:entree,sortie,ajustement',
            'quantite'       => 'required|numeric|min:0.001',
            'motif'          => 'nullable|string|max:200',
            'date_mouvement' => 'nullable|date',
        ])->validate();

        $article = StockCuisine::findOrFail($id);

        $nouvelleQte = match ($validated['type']) {
            'entree'     => $article->quantite_stock + $validated['quantite'],
            'sortie'     => $article->quantite_stock - $validated['quantite'],
            'ajustement' => $validated['quantite'],
        };

        if ($nouvelleQte < 0) {
            return [
                'error'  => "Stock insuffisant : {$article->quantite_stock} {$article->unite} disponible(s)",
                'code'   => 'STOCK_INSUFFISANT',
                'status' => 422,
            ];
        }

        MouvementStockCuisine::create([
            'tenant_id'      => config('tenant.current_id'),
            'article_id'     => $article->id,
            'type'           => $validated['type'],
            'quantite'       => $validated['quantite'],
            'motif'          => $validated['motif'] ?? null,
            'saisie_par'     => Auth::id(),
            'date_mouvement' => $validated['date_mouvement'] ?? today(),
        ]);

        $article->update(['quantite_stock' => $nouvelleQte]);

        return [
            'article'       => $article->fresh(),
            'mouvement'     => $validated['type'],
            'quantite'      => $validated['quantite'],
            'nouveau_stock' => $nouvelleQte,
            'en_alerte'     => $nouvelleQte <= $article->seuil_alerte,
            'article_nom'   => $article->article,
            'article_unite' => $article->unite,
        ];
    }

    /**
     * @return array{alertes: mixed, nb_alertes: int, message: string}
     */
    public function alertesStock(): array
    {
        $articles = StockCuisine::enAlerte()
            ->orderBy('quantite_stock')
            ->get()
            ->map(fn($a) => array_merge($a->toArray(), [
                'deficit'     => max(0, $a->seuil_alerte - $a->quantite_stock),
                'perime_soon' => $a->perime_soon,
            ]));

        return [
            'alertes'    => $articles,
            'nb_alertes' => $articles->count(),
            'message'    => "{$articles->count()} article(s) en alerte de stock",
        ];
    }
}
