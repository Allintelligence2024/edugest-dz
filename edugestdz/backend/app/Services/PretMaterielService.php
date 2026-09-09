<?php

namespace App\Services;

use App\Models\PretMateriel;
use App\Models\ArticleStock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades Auth;

/**
 * Service de gestion des prêts de matériel.
 * Helper `table()` répliquant le scope tenant fail-closed.
 */
class PretMaterielService
{
    public const TABLE_PRET_MATERIEL = 'prets_materiels';
    public const TABLE_MOUVEMENT_STOCK = 'mouvements_stocks';

    /**
     * @var Builder
     */
    private $builder;

    private function table(string $table): Builder
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

    /**
     * Liste paginée des prêts avec filtres.
     */
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'statut' => 'nullable|in:en_cours, rendu, annule',
        ]);

        $query = $this->table('prets_materiels')->with('article:id,nom,reference,unite');

        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        $paginator = $query->orderByDesc('date_pret')
            ->paginate($request->per_page ?? 20);

        $enRetard = $this->table('prets_materiels')
            ->where('statut', 'en_cours')
            ->where('date_retour_prevue', '<', today())
            ->count();

        return [
            'paginator' => $paginator,
            'enRetard'  => $enRetard,
        ];
    }

    /**
     * Enregistrement d'un nouveau prêt.
     */
    public function creerPret(array $validated): array
    {
        $article = ArticleStock::findOrFail($validated['article_id']);

        if ($article->quantite_stock < $validated['quantite']) {
            return ['error' => "Stock insuffisant : {$article->quantite_stock} {$article->unite} disponible(s)", 'code' => 'STOCK_INSUFFISANT', 'status' => 422];
        }

        return DB::transaction(function () use ($validated, $article) {
            $avant = $article->quantite_stock;

            $this->table('prets_materiels')->create(array_merge($validated, [
                'tenant_id'  => config('tenant.current_id'),
                'date_pret'  => $validated['date_pret'] ?? today(),
                'statut'     => 'en_cours',
            ]));

            $article->update(['quantite_stock' => $avant - $validated['quantite']]);

            $this->table('mouvements_stocks')->create([
                'tenant_id'      => config('tenant.current_id'),
                'article_id'     => $article->id,
                'type'           => 'sortie',
                'quantite'       => $validated['quantite'],
                'quantite_avant' => $avant,
                'quantite_apres' => $avant - $validated['quantite'],
                'motif'          => 'Prêt de matériel',
                'saisie_par'     => Auth::id(),
                'date_mouvement' => today(),
            ]);

            return ['success' => "Prêt enregistré pour '{$article->nom}'"];
        });
    }

    /**
     * Enregistrement du retour d'un prêt.
     */
    public function retourPret(string $id, array $validated): array
    {
        $pret = PretMateriel::with('article')->findOrFail($id);
        $article = $pret->article;

        if ($pret->statut !== 'en_cours') {
            return ['error' => 'Ce prêt est déjà clôturé', 'code' => 'DEJA_CLOTURE', 'status' => 409];
        }

        return DB::transaction(function () use ($pret, $article, $validated) {
            $avant = $article->quantite_stock;

            $pret->update([
                'statut'                 => 'rendu',
                'date_retour_effective'  => $validated['date_retour'] ?? today(),
                'note'                   => $validated['note'] ?? $pret->note,
            ]);

            $article->update(['quantite_stock' => $avant + $pret->quantite]);

            if (isset($validated['etat_retour'])) {
                $article->update(['etat' => $validated['etat_retour']]);
            }

            $this->table('mouvements_stocks')->create([
                'tenant_id'      => config('tenant.current_id'),
                'article_id'     => $article->id,
                'type'           => 'entree',
                'quantite'       => $pret->quantite,
                'quantite_avant' => $avant,
                'quantite_apres' => $avant + $pret->quantite,
                'motif'          => 'Retour de prêt',
                'saisie_par'     => Auth::id(),
                'date_mouvement' => today(),
            ]);

            return ['success' => "Retour enregistré pour '{$article->nom}'"];
        });
    }
}