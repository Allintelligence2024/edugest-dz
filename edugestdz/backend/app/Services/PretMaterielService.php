<?php

namespace App\Services;

use App\Models\ArticleStock;
use App\Models\MouvementStock;
use App\Models\PretMateriel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PretMaterielService
{
    /**
     * @return array{paginator: LengthAwarePaginator, nb_en_retard: int}
     */
    public function index(Request $request): array
    {
        $paginator = PretMateriel::with('article:id,nom,reference,unite')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_pret')
            ->paginate($request->per_page ?? 20);

        $enRetard = PretMateriel::where('statut', 'en_cours')
            ->where('date_retour_prevue', '<', today())
            ->count();

        return ['paginator' => $paginator, 'nb_en_retard' => $enRetard];
    }

    /**
     * @return array{success: string}|array{error: string, code: string, status: int}
     */
    public function creerPret(array $data): array
    {
        $validated = Validator::make($data, [
            'article_id'          => 'required|uuid|exists:articles_stock,id',
            'emprunteur_id'       => 'nullable|uuid',
            'type_emprunteur'     => 'required|in:enseignant,personnel,externe',
            'nom_emprunteur'      => 'nullable|string|max:150',
            'quantite'            => 'required|integer|min:1',
            'date_pret'           => 'nullable|date',
            'date_retour_prevue'  => 'required|date|after_or_equal:date_pret',
            'note'                => 'nullable|string|max:300',
        ])->validate();

        $article = ArticleStock::findOrFail($validated['article_id']);

        if ($article->quantite_stock < $validated['quantite']) {
            return [
                'error'  => "Stock insuffisant : {$article->quantite_stock} {$article->unite} disponible(s)",
                'code'   => 'STOCK_INSUFFISANT',
                'status' => 422,
            ];
        }

        DB::transaction(function () use ($article, $validated) {
            PretMateriel::create([
                'tenant_id'           => config('tenant.current_id'),
                'article_id'          => $validated['article_id'],
                'emprunteur_id'       => $validated['emprunteur_id'] ?? null,
                'type_emprunteur'     => $validated['type_emprunteur'],
                'nom_emprunteur'      => $validated['nom_emprunteur'] ?? null,
                'quantite'            => $validated['quantite'],
                'date_pret'           => $validated['date_pret'] ?? today(),
                'date_retour_prevue'  => $validated['date_retour_prevue'],
                'note'                => $validated['note'] ?? null,
                'statut'              => 'en_cours',
            ]);

            $avant = $article->quantite_stock;
            $article->update(['quantite_stock' => $avant - $validated['quantite']]);

            MouvementStock::create([
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
        });

        return ['success' => "Prêt enregistré pour '{$article->nom}'"];
    }

    /**
     * @return array{success: string}|array{error: string, code: string, status: int}
     */
    public function retourPret(string $id, array $data): array
    {
        $validated = Validator::make($data, [
            'date_retour'  => 'nullable|date',
            'etat_retour'  => 'nullable|in:bon,use,hors_service',
            'note'         => 'nullable|string|max:300',
        ])->validate();

        $pret = PretMateriel::with('article')->findOrFail($id);

        /** @var ArticleStock $article */
        $article = $pret->article;

        if ($pret->statut !== 'en_cours') {
            return [
                'error'  => 'Ce prêt est déjà clôturé',
                'code'   => 'DEJA_CLOTURE',
                'status' => 409,
            ];
        }

        DB::transaction(function () use ($pret, $article, $validated) {
            $pret->update([
                'statut'                 => 'rendu',
                'date_retour_effective'  => $validated['date_retour'] ?? today(),
                'note'                   => $validated['note'] ?? $pret->note,
            ]);

            $avant = $article->quantite_stock;
            $article->update(['quantite_stock' => $avant + $pret->quantite]);

            if (isset($validated['etat_retour'])) {
                $article->update(['etat' => $validated['etat_retour']]);
            }

            MouvementStock::create([
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
        });

        return ['success' => "Retour enregistré pour '{$article->nom}'"];
    }
}
