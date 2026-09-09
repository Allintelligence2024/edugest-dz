<?php

namespace App\Services;

use App\Models\BonCommande;
use App\Models\LigneBonCommande;
use App\Models\ArticleStock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service de gestion des bons de commande.
 * Helper `table()` répliquant le scope tenant fail-closed.
 */
class BonCommandeService
{
    public const TABLE_BON_COMMANDE = 'bons_commandes';
    public const TABLE_LIGNE_BON_COMMANDE = 'lignes_bons_commandes';
    public const TABLE_MOUVEMENT_STOCK = 'mouvements_stocks';

    /**
     * @var Builder
     */
    private $builder;

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

    /**
     * Liste paginée des bons de commande avec filtres.
     */
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'statut' => 'nullable|in:brouillon,envoye,recu,partiel,annule',
        ]);

        $query = $this->table('bons_commandes')->with('lignes');

        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        $paginator = $query->orderByDesc('date_commande')
            ->paginate($request->per_page ?? 20);

        return ['paginator' => $paginator];
    }

    /**
     * Création d'un bon de commande.
     */
    public function creerBon(array $validated): array
    {
        $total = collect($validated['lignes'])->sum(
            fn($l) => $l['quantite'] * $l['prix_unitaire']
        );

        return DB::transaction(function () use ($validated, $total) {
            $bon = BonCommande::create([
                'tenant_id'             => config('tenant.current_id'),
                'numero'                => BonCommande::genererNumero(),
                'fournisseur'           => $validated['fournisseur'],
                'fournisseur_contact'   => $validated['fournisseur_contact'] ?? null,
                'date_commande'         => $validated['date_commande'] ?? today(),
                'date_livraison_prevue' => $validated['date_livraison_prevue'] ?? null,
                'montant_total'         => $total,
                'statut'                => 'brouillon',
                'note'                  => $validated['note'] ?? null,
            ]);

            foreach ($validated['lignes'] as $ligne) {
                $this->table('lignes_bons_commandes')->create([
                    'bon_commande_id' => $bon->id,
                    'article_id'      => $ligne['article_id'] ?? null,
                    'designation'     => $ligne['designation'],
                    'quantite'        => $ligne['quantite'],
                    'prix_unitaire'   => $ligne['prix_unitaire'],
                    'total'           => $ligne['quantite'] * $ligne['prix_unitaire'],
                ]);
            }

            return $bon;
        });
    }

    /**
     * Mise à jour du statut d'un bon de commande.
     */
    public function statutBon(array $validated, string $id): array
    {
        $statut = $validated['statut'] ?? null;
        if (!in_array($statut, ['brouillon','envoye','recu','partiel','annule'])) {
            return ['error' => 'Statut invalide', 'bon' => null];
        }
        $bon = BonCommande::findOrFail($id);
        $bon->update(['statut' => $statut]);

        if ($statut === 'recu') {
            foreach ($bon->lignes as $ligne) {
                if ($ligne->article_id) {
                    $article = ArticleStock::find($ligne->article_id);
                    if ($article) {
                        $avant = $article->quantite_stock;
                        $article->update(['quantite_stock' => $avant + $ligne->quantite]);
                        $this->table('mouvements_stocks')->create([
                            'tenant_id'      => config('tenant.current_id'),
                            'article_id'     => $article->id,
                            'type'           => 'entree',
                            'quantite'       => $ligne->quantite,
                            'quantite_avant' => $avant,
                            'quantite_apres' => $avant + $ligne->quantite,
                            'motif'          => "Réception BC {$bon->numero}",
                            'reference_doc'  => $bon->numero,
                            'saisie_par'     => Auth::id(),
                            'date_mouvement' => today(),
                        ]);
                    }
                }
            }
        }

        return ['success' => "Bon {$bon->numero} — statut : {$statut}", 'bon' => $bon->fresh('lignes')];
    }

    /**
     * Génération PDF d'un bon de commande.
     */
    public function pdfBon(string $id): array
    {
        $bon = BonCommande::with('lignes.article')->findOrFail($id);
        $tenant  = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        // Note : la vue PDF et le stockage sont gérés par le contrôleur ; on renvoie
        // les données nécessaires au contrôleur.
        return [
            'bon'    => $bon,
            'tenant' => $tenant,
        ];
    }
}