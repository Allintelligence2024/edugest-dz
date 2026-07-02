<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ArticleStock;
use App\Models\BonCommande;
use App\Models\LigneBonCommande;
use App\Models\MouvementStock;
use App\Models\PretMateriel;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StockInventaireController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/stock/articles",
     *     summary="Liste des articles en stock",
     *     tags={"Stock"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="categorie",     in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="en_alerte",     in="query", @OA\Schema(type="boolean", description="true = articles sous le seuil")),
     *     @OA\Parameter(name="per_page",      in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Articles paginés", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function index(Request $request): JsonResponse
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

        return $this->paginatedResponse($paginator, 'Articles récupérés', ['stats' => $stats]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
        ]);

        $article = DB::transaction(function () use ($validated) {
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
                    'saisie_par'     => auth()->id(),
                    'date_mouvement' => today(),
                ]);
            }

            return $art;
        });

        return $this->created([
            'article'          => $article,
            'qr_code'          => $article->qr_code,
            'reference'        => $article->reference,
            'categorie_label'  => $article->categorie_label,
        ], "Article '{$article->nom}' créé · Réf : {$article->reference}");
    }

    public function show(string $id): JsonResponse
    {
        $article = ArticleStock::with([
            'mouvements' => fn($q) => $q->orderByDesc('date_mouvement')->limit(20),
            'pretsEnCours',
        ])->findOrFail($id);

        return $this->success([
            'article'        => $article,
            'etat_label'     => $article->etat_label,
            'categorie_label'=> $article->categorie_label,
            'en_alerte'      => $article->en_alerte,
            'valeur_totale'  => $article->valeur_totale,
            'nb_prets_cours' => $article->pretsEnCours->count(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $article   = ArticleStock::findOrFail($id);
        $validated = $request->validate([
            'nom'             => 'sometimes|string|max:150',
            'etat'            => 'sometimes|in:bon,use,hors_service,en_reparation',
            'localisation'    => 'nullable|string|max:100',
            'valeur_unitaire' => 'nullable|numeric|min:0',
            'quantite_minimum'=> 'sometimes|integer|min:0',
            'fournisseur'     => 'nullable|string|max:150',
            'est_immobilise'  => 'sometimes|boolean',
            'note'            => 'nullable|string|max:500',
        ]);

        $article->update($validated);
        return $this->success($article->fresh(), 'Article mis à jour');
    }

    public function destroy(string $id): JsonResponse
    {
        $article = ArticleStock::findOrFail($id);
        if ($article->pretsEnCours()->exists()) {
            return $this->error(
                'Impossible de supprimer : des prêts sont en cours',
                'HAS_PRETS', 422
            );
        }
        $nom = $article->nom;
        $article->delete();
        return $this->success(null, "Article '{$nom}' supprimé");
    }

    public function parQrCode(string $qrCode): JsonResponse
    {
        $article = ArticleStock::where('qr_code', $qrCode)->firstOrFail();
        return $this->success([
            'article'        => $article,
            'etat_label'     => $article->etat_label,
            'categorie_label'=> $article->categorie_label,
            'en_alerte'      => $article->en_alerte,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock/articles/{id}/mouvement",
     *     summary="Enregistrer un mouvement de stock (entrée / sortie / ajustement)",
     *     tags={"Stock"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","quantite"},
     *             @OA\Property(property="type",       type="string",  enum={"entree","sortie","ajustement","transfert","perte"}),
     *             @OA\Property(property="quantite",   type="integer", example=10),
     *             @OA\Property(property="motif",      type="string",  nullable=true),
     *             @OA\Property(property="reference_doc", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Mouvement enregistré", @OA\JsonContent(ref="#/components/schemas/SuccessResponse"))
     * )
     */
    public function mouvement(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'type'          => 'required|in:entree,sortie,ajustement,transfert,perte',
            'quantite'      => 'required|integer|min:1',
            'motif'         => 'nullable|string|max:200',
            'reference_doc' => 'nullable|string|max:100',
            'date_mouvement'=> 'nullable|date',
        ]);

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
            return $this->error(
                "Stock insuffisant : {$avant} {$article->unite} disponible(s)",
                'STOCK_INSUFFISANT', 422
            );
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
                'saisie_par'     => auth()->id(),
                'date_mouvement' => $validated['date_mouvement'] ?? today(),
            ]);
        });

        return $this->success([
            'article'       => $article->fresh(),
            'quantite_avant'=> $avant,
            'quantite_apres'=> $apres,
            'en_alerte'     => $apres <= $article->quantite_minimum,
        ], "Stock mis à jour : {$article->nom} — {$avant} → {$apres} {$article->unite}");
    }

    public function historique(Request $request, string $id): JsonResponse
    {
        $article   = ArticleStock::findOrFail($id);
        $paginator = MouvementStock::where('article_id', $article->id)
            ->orderByDesc('date_mouvement')
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse($paginator, 'Historique mouvements', [
            'article' => ['id' => $article->id, 'nom' => $article->nom, 'stock_actuel' => $article->quantite_stock],
        ]);
    }

    public function alertes(): JsonResponse
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

        return $this->success([
            'articles'   => $articles,
            'nb_alertes' => $articles->count(),
        ], "{$articles->count()} article(s) sous le seuil minimum");
    }

    public function indexPrets(Request $request): JsonResponse
    {
        $paginator = PretMateriel::with('article:id,nom,reference,unite')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_pret')
            ->paginate($request->per_page ?? 20);

        $enRetard = PretMateriel::where('statut', 'en_cours')
            ->where('date_retour_prevue', '<', today())
            ->count();

        return $this->paginatedResponse($paginator, 'Prêts récupérés', [
            'nb_en_retard' => $enRetard,
        ]);
    }

    public function creerPret(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id'          => 'required|uuid|exists:articles_stock,id',
            'emprunteur_id'       => 'nullable|uuid',
            'type_emprunteur'     => 'required|in:enseignant,personnel,externe',
            'nom_emprunteur'      => 'nullable|string|max:150',
            'quantite'            => 'required|integer|min:1',
            'date_pret'           => 'nullable|date',
            'date_retour_prevue'  => 'required|date|after_or_equal:date_pret',
            'note'                => 'nullable|string|max:300',
        ]);

        $article = ArticleStock::findOrFail($validated['article_id']);

        if ($article->quantite_stock < $validated['quantite']) {
            return $this->error(
                "Stock insuffisant : {$article->quantite_stock} {$article->unite} disponible(s)",
                'STOCK_INSUFFISANT', 422
            );
        }

        DB::transaction(function () use ($article, $validated) {
            PretMateriel::create(array_merge($validated, [
                'tenant_id'  => config('tenant.current_id'),
                'date_pret'  => $validated['date_pret'] ?? today(),
                'statut'     => 'en_cours',
            ]));

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
                'saisie_par'     => auth()->id(),
                'date_mouvement' => today(),
            ]);
        });

        return $this->created(null, "Prêt enregistré pour '{$article->nom}'");
    }

    public function retourPret(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'date_retour'  => 'nullable|date',
            'etat_retour'  => 'nullable|in:bon,use,hors_service',
            'note'         => 'nullable|string|max:300',
        ]);

        $pret    = PretMateriel::with('article')->findOrFail($id);
        $article = $pret->article;

        if ($pret->statut !== 'en_cours') {
            return $this->error('Ce prêt est déjà clôturé', 'DEJA_CLOTURE', 409);
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
                'saisie_par'     => auth()->id(),
                'date_mouvement' => today(),
            ]);
        });

        return $this->success(null, "Retour enregistré pour '{$article->nom}'");
    }

    public function indexBons(Request $request): JsonResponse
    {
        $paginator = BonCommande::with('lignes')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_commande')
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse($paginator, 'Bons de commande récupérés');
    }

    public function creerBon(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
        ]);

        $bon = DB::transaction(function () use ($validated) {
            $total = collect($validated['lignes'])->sum(
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

        return $this->created($bon->load('lignes'), "Bon de commande {$bon->numero} créé");
    }

    public function statutBon(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'statut' => 'required|in:brouillon,envoye,recu,partiel,annule',
        ]);

        $bon = BonCommande::findOrFail($id);
        $bon->update(['statut' => $validated['statut']]);

        if ($validated['statut'] === 'recu') {
            foreach ($bon->lignes as $ligne) {
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
                            'saisie_par'     => auth()->id(),
                            'date_mouvement' => today(),
                        ]);
                    }
                }
            }
        }

        return $this->success($bon->fresh('lignes'), "Bon {$bon->numero} — statut : {$validated['statut']}");
    }

    public function pdfBon(string $id)
    {
        $bon    = BonCommande::with('lignes.article')->findOrFail($id);
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $pdf  = Pdf::loadView('pdf.bon_commande', compact('bon', 'tenant'))->setPaper('A4', 'portrait');
        $path = "bons_commande/{$bon->tenant_id}/{$bon->numero}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        return response()->download(storage_path('app/public/' . $path));
    }

    public function rapportInventaire(Request $request)
    {
        $annee  = (int) ($request->annee ?? now()->year);
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $articles = ArticleStock::where('actif', true)
            ->orderBy('categorie')->orderBy('nom')
            ->get();

        $parCategorie = $articles->groupBy('categorie')->map(fn($grp) => [
            'articles'     => $grp,
            'nb_articles'  => $grp->count(),
            'valeur_totale'=> $grp->sum('valeur_totale'),
            'nb_alertes'   => $grp->filter(fn($a) => $a->en_alerte)->count(),
        ]);

        $pdf = Pdf::loadView('pdf.rapport_inventaire', [
            'articles'     => $articles,
            'par_categorie'=> $parCategorie,
            'tenant'       => $tenant,
            'annee'        => $annee,
            'date_rapport' => today()->format('d/m/Y'),
            'valeur_totale'=> $articles->sum('valeur_totale'),
            'nb_total'     => $articles->count(),
            'nb_alertes'   => $articles->filter(fn($a) => $a->en_alerte)->count(),
        ])->setPaper('A4', 'portrait');

        $path = "rapports/inventaire_{$annee}_" . now()->format('Ymd') . ".pdf";
        Storage::disk('public')->put($path, $pdf->output());

        return response()->download(storage_path('app/public/' . $path));
    }

    public function dashboard(): JsonResponse
    {
        $alertes      = ArticleStock::where('actif', true)->enAlerte()->count();
        $pretsRetard  = PretMateriel::where('statut', 'en_cours')
            ->where('date_retour_prevue', '<', today())->count();
        $bonsPendants = BonCommande::whereIn('statut', ['brouillon', 'envoye'])->count();

        $valeurTotale = ArticleStock::where('actif', true)
            ->selectRaw('SUM(quantite_stock * COALESCE(valeur_unitaire, 0)) as total')
            ->value('total') ?? 0;

        $parCategorie = ArticleStock::where('actif', true)
            ->selectRaw('categorie, COUNT(*) as nb, SUM(quantite_stock) as qte')
            ->groupBy('categorie')->get();

        $derniersMovements = MouvementStock::with('article:id,nom')
            ->orderByDesc('created_at')->limit(5)->get();

        return $this->success([
            'alertes_stock'    => $alertes,
            'prets_en_retard'  => $pretsRetard,
            'bons_pendants'    => $bonsPendants,
            'valeur_totale_da' => (float) $valeurTotale,
            'par_categorie'    => $parCategorie,
            'derniers_mouvements' => $derniersMovements,
        ], 'Tableau de bord stock & inventaire');
    }
}
