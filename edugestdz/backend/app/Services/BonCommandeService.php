<?php

namespace App\Services;

use App\Models\ArticleStock;
use App\Models\BonCommande;
use App\Models\LigneBonCommande;
use App\Models\MouvementStock;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BonCommandeService
{
    /**
     * @return array{paginator: LengthAwarePaginator}
     */
    public function index(Request $request): array
    {
        $paginator = BonCommande::with('lignes')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_commande')
            ->paginate($request->per_page ?? 20);

        return ['paginator' => $paginator];
    }

    /**
     * @return array{bon: BonCommande}
     */
    public function creerBon(array $data): array
    {
        $validated = Validator::make($data, [
            'fournisseur'            => 'required|string|max:150',
            'fournisseur_contact'    => 'nullable|string|max:150',
            'date_commande'          => 'nullable|date',
            'date_livraison_prevue'  => 'nullable|date',
            'note'                   => 'nullable|string|max:500',
            'lignes'                 => 'required|array|min:1',
            'lignes.*.designation'   => 'required|string|max:200',
            'lignes.*.article_id'    => 'nullable|uuid',
            'lignes.*.quantite'      => 'required|integer|min:1',
            'lignes.*.prix_unitaire' => 'required|numeric|min:0',
        ])->validate();

        $bon = DB::transaction(function () use ($validated): BonCommande {
            /** @var array<int, array<string, mixed>> $lignesData */
            $lignesData = $validated['lignes'];

            $total = collect($lignesData)->sum(
                fn($l) => $l['quantite'] * $l['prix_unitaire']
            );

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
                LigneBonCommande::create([
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

        return ['bon' => $bon->load('lignes')];
    }

    /**
     * @return array{bon: BonCommande, statut: mixed}
     */
    public function statutBon(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'statut' => 'required|in:brouillon,envoye,recu,partiel,annule',
        ])->validate();

        $bon = BonCommande::findOrFail($id);
        $bon->update(['statut' => $validated['statut']]);

        if ($validated['statut'] === 'recu') {
            foreach ($bon->lignes as $ligne) {
                /** @var LigneBonCommande $ligne */
                if ($ligne->article_id) {
                    $article = ArticleStock::find($ligne->article_id);
                    if ($article) {
                        $avant = $article->quantite_stock;
                        $article->update(['quantite_stock' => $avant + $ligne->quantite]);
                        MouvementStock::create([
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

        return ['bon' => $bon->fresh('lignes'), 'statut' => $validated['statut']];
    }
}
