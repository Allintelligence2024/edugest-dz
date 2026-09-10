<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Eleve;
use App\Models\InscriptionCantine;
use App\Models\MenuCantine;
use App\Models\MouvementStockCuisine;
use App\Models\RepasJournalier;
use App\Models\StockCuisine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CantineController extends BaseApiController
{
    public function indexMenus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'debut'    => 'nullable|date',
            'fin'      => 'nullable|date|after_or_equal:debut',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $debut = $validated['debut'] ?? today()->startOfWeek()->toDateString();
        $fin   = $validated['fin']   ?? today()->endOfWeek()->toDateString();

        $paginator = MenuCantine::whereBetween('date_repas', [$debut, $fin])
            ->orderBy('date_repas')
            ->paginate($validated['per_page'] ?? 30);

        return $this->paginatedResponse($paginator, "Menus du {$debut} au {$fin}", [
            'periode' => compact('debut', 'fin'),
        ]);
    }

    public function menuSemaine(Request $request): JsonResponse
    {
        $semaine = $request->filled('semaine')
            ? Carbon::parse($request->semaine)->startOfWeek()
            : now()->startOfWeek();

        $debut = $semaine->toDateString();
        $fin   = $semaine->copy()->endOfWeek()->toDateString();

        $menus = MenuCantine::publies()
            ->semaine($debut, $fin)
            ->orderBy('date_repas')
            ->get()
            ->groupBy(fn($m) => $m->date_repas->format('Y-m-d'))
            ->map(fn($menus, $date) => [
                'date'  => $date,
                'label' => Carbon::parse($date)->translatedFormat('l d/m'),
                'repas' => $menus->values(),
            ])
            ->values();

        return $this->success([
            'semaine_debut' => $debut,
            'semaine_fin'   => $fin,
            'jours'         => $menus,
        ], "Menu semaine du {$debut} au {$fin}");
    }

    public function storeMenu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_repas'         => 'required|date',
            'type_repas'         => 'nullable|in:dejeuner,diner,petit_dejeuner',
            'plat_principal'     => 'required|string|max:200',
            'accompagnement'     => 'nullable|string|max:200',
            'dessert'            => 'nullable|string|max:150',
            'boisson'            => 'nullable|string|max:100',
            'prix_unitaire'      => 'required|numeric|min:0',
            'nb_couverts_prevus' => 'nullable|integer|min:0',
            'allergenes'         => 'nullable|string|max:300',
            'note'               => 'nullable|string|max:500',
        ]);

        $menu = MenuCantine::create($validated);

        return $this->created($menu, "Menu du {$menu->date_repas->format('d/m/Y')} cree");
    }

    public function updateMenu(Request $request, string $id): JsonResponse
    {
        $menu      = MenuCantine::findOrFail($id);
        $validated = $request->validate([
            'plat_principal'     => 'sometimes|string|max:200',
            'accompagnement'     => 'nullable|string|max:200',
            'dessert'            => 'nullable|string|max:150',
            'prix_unitaire'      => 'sometimes|numeric|min:0',
            'nb_couverts_prevus' => 'nullable|integer|min:0',
            'publie'             => 'sometimes|boolean',
            'note'               => 'nullable|string|max:500',
        ]);

        $menu->update($validated);
        return $this->success($menu->fresh(), 'Menu mis a jour');
    }

    public function destroyMenu(string $id): JsonResponse
    {
        $menu = MenuCantine::findOrFail($id);
        $menu->delete();
        return $this->success(null, "Menu du {$menu->date_repas->format('d/m/Y')} supprime");
    }

    public function indexInscriptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'regime'   => 'nullable|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'actif'    => 'nullable|boolean',
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = InscriptionCantine::with('eleve:id,nom,prenom,photo_url,niveau_scolaire')
            ->when(isset($validated['actif']), fn($q) => $q->where('actif', $validated['actif']))
            ->when(!empty($validated['regime']), fn($q) => $q->where('regime', $validated['regime']));

        if (!empty($validated['search'])) {
            $query->whereHas('eleve', fn($q) => $q
                ->where('nom', 'like', "%{$validated['search']}%")
                ->orWhere('prenom', 'like', "%{$validated['search']}%")
            );
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total_inscrits' => InscriptionCantine::where('actif', true)->count(),
            'par_regime'     => InscriptionCantine::where('actif', true)
                ->selectRaw('regime, COUNT(*) as total')
                ->groupBy('regime')->pluck('total', 'regime'),
        ];

        return $this->paginatedResponse($paginator, 'Inscriptions cantine', ['stats' => $stats]);
    }

    public function inscrireEleve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'        => 'required|uuid|exists:eleves,id',
            'type_abonnement' => 'required|in:mensuel,journalier',
            'regime'          => 'required|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'allergies'       => 'nullable|string|max:300',
            'date_debut'      => 'required|date',
            'date_fin'        => 'nullable|date|after:date_debut',
            'tarif_mensuel'   => 'required|numeric|min:0',
            'note'            => 'nullable|string|max:300',
        ]);

        $eleve = Eleve::findOrFail($validated['eleve_id']);

        $dejaInscrit = InscriptionCantine::where('eleve_id', $validated['eleve_id'])
            ->where('actif', true)
            ->exists();

        if ($dejaInscrit) {
            return $this->error(
                "{$eleve->prenom} {$eleve->nom} est deja inscrit(e) a la cantine",
                'DEJA_INSCRIT', 409
            );
        }

        $inscription = InscriptionCantine::create($validated);

        return $this->created([
            'inscription'   => $inscription,
            'eleve'         => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'regime_label'  => $inscription->regime_label,
        ], "{$eleve->prenom} {$eleve->nom} inscrit(e) a la cantine ({$inscription->regime_label})");
    }

    public function updateInscription(Request $request, string $id): JsonResponse
    {
        $inscription = InscriptionCantine::findOrFail($id);
        $validated   = $request->validate([
            'regime'         => 'sometimes|in:normal,sans_porc,vegetarien,sans_gluten,autre',
            'allergies'      => 'nullable|string|max:300',
            'actif'          => 'sometimes|boolean',
            'tarif_mensuel'  => 'sometimes|numeric|min:0',
            'date_fin'       => 'nullable|date',
            'note'           => 'nullable|string|max:300',
        ]);

        $inscription->update($validated);
        return $this->success($inscription->fresh('eleve'), 'Inscription mise a jour');
    }

    public function desinscrireEleve(string $id): JsonResponse
    {
        $inscription = InscriptionCantine::with('eleve')->findOrFail($id);
        $inscription->update(['actif' => false, 'date_fin' => today()]);
        $nom = "{$inscription->eleve->prenom} {$inscription->eleve->nom}";
        return $this->success(null, "{$nom} desinscrit(e) de la cantine");
    }

    public function pointer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'                   => 'nullable|date',
            'type_repas'             => 'required|in:dejeuner,diner,petit_dejeuner',
            'pointages'              => 'required|array|min:1',
            'pointages.*.eleve_id'   => 'required|uuid|exists:eleves,id',
            'pointages.*.present'    => 'required|boolean',
        ]);

        $date = $validated['date'] ?? today()->toDateString();
        $menu = MenuCantine::where('date_repas', $date)
            ->where('type_repas', $validated['type_repas'])
            ->first();

        $enregistres = 0;
        foreach ($validated['pointages'] as $p) {
            RepasJournalier::updateOrCreate(
                [
                    'tenant_id'  => config('tenant.current_id'),
                    'eleve_id'   => $p['eleve_id'],
                    'date_repas' => $date,
                    'type_repas' => $validated['type_repas'],
                ],
                [
                    'menu_id'       => $menu?->id,
                    'present'       => $p['present'],
                    'prix_applique' => $p['present'] ? ($menu?->prix_unitaire ?? 0) : 0,
                    'signale_par'   => 'admin',
                ]
            );
            $enregistres++;
        }

        return $this->success([
            'date'        => $date,
            'type_repas'  => $validated['type_repas'],
            'enregistres' => $enregistres,
            'presents'    => collect($validated['pointages'])->where('present', true)->count(),
            'absents'     => collect($validated['pointages'])->where('present', false)->count(),
        ], "{$enregistres} repas pointe(s) pour le {$date}");
    }

    public function pointageDate(string $date): JsonResponse
    {
        $repas = RepasJournalier::with('eleve:id,nom,prenom', 'menu')
            ->where('date_repas', $date)
            ->get();

        $menu = MenuCantine::where('date_repas', $date)
            ->where('type_repas', 'dejeuner')
            ->first();

        return $this->success([
            'date'  => $date,
            'menu'  => $menu,
            'repas' => $repas,
            'stats' => [
                'total'   => $repas->count(),
                'presents'=> $repas->where('present', true)->count(),
                'absents' => $repas->where('present', false)->count(),
                'ca_jour' => $repas->where('present', true)->sum('prix_applique'),
            ],
        ]);
    }

    public function indexStock(Request $request): JsonResponse
    {
        $query = StockCuisine::query();

        if ($request->filled('categorie')) {
            $query->where('categorie', $request->categorie);
        }

        $articles = $query->orderBy('article')->get()->map(fn($a) => array_merge(
            $a->toArray(),
            ['en_alerte' => $a->en_alert, 'perime_soon' => $a->perime_soon]
        ));

        return $this->success([
            'articles'    => $articles,
            'nb_alertes'  => $articles->where('en_alerte', true)->count(),
            'nb_articles' => $articles->count(),
        ], 'Stock cuisine recupere');
    }

    public function storeStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article'         => 'required|string|max:150',
            'categorie'       => 'required|in:legumes,viandes,poissons,produits_laitiers,cereales,condiments,boissons,autres',
            'unite'           => 'required|string|max:20',
            'quantite_stock'  => 'required|numeric|min:0',
            'seuil_alerte'    => 'required|numeric|min:0',
            'prix_unitaire'   => 'nullable|numeric|min:0',
            'fournisseur'     => 'nullable|string|max:150',
            'date_peremption' => 'nullable|date|after:today',
        ]);

        $article = StockCuisine::create($validated);
        return $this->created($article, "Article '{$article->article}' ajoute au stock");
    }

    public function mouvementStock(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'type'           => 'required|in:entree,sortie,ajustement',
            'quantite'       => 'required|numeric|min:0.001',
            'motif'          => 'nullable|string|max:200',
            'date_mouvement' => 'nullable|date',
        ]);

        $article = StockCuisine::findOrFail($id);

        $nouvelleQte = match ($validated['type']) {
            'entree'     => $article->quantite_stock + $validated['quantite'],
            'sortie'     => $article->quantite_stock - $validated['quantite'],
            'ajustement' => $validated['quantite'],
        };

        if ($nouvelleQte < 0) {
            return $this->error(
                "Stock insuffisant : {$article->quantite_stock} {$article->unite} disponible(s)",
                'STOCK_INSUFFISANT', 422
            );
        }

        MouvementStockCuisine::create([
            'tenant_id'      => config('tenant.current_id'),
            'article_id'     => $article->id,
            'type'           => $validated['type'],
            'quantite'       => $validated['quantite'],
            'motif'          => $validated['motif'] ?? null,
            'saisie_par'     => auth()->id(),
            'date_mouvement' => $validated['date_mouvement'] ?? today(),
        ]);

        $article->update(['quantite_stock' => $nouvelleQte]);

        return $this->success([
            'article'       => $article->fresh(),
            'mouvement'     => $validated['type'],
            'quantite'      => $validated['quantite'],
            'nouveau_stock' => $nouvelleQte,
            'en_alerte'     => $nouvelleQte <= $article->seuil_alerte,
        ], "Stock {$article->article} mis a jour : {$nouvelleQte} {$article->unite}");
    }

    public function alertesStock(): JsonResponse
    {
        $articles = StockCuisine::enAlerte()
            ->orderBy('quantite_stock')
            ->get()
            ->map(fn($a) => array_merge($a->toArray(), [
                'deficit'     => max(0, $a->seuil_alerte - $a->quantite_stock),
                'perime_soon' => $a->perime_soon,
            ]));

        return $this->success([
            'alertes'    => $articles,
            'nb_alertes' => $articles->count(),
        ], "{$articles->count()} article(s) en alerte de stock");
    }

    public function dashboard(): JsonResponse
    {
        $today = today();

        $menuDuJour = MenuCantine::where('date_repas', $today)
            ->where('type_repas', 'dejeuner')
            ->first();

        $inscritsActifs = InscriptionCantine::where('actif', true)->count();

        $presentsAujourdhui = RepasJournalier::where('date_repas', $today)
            ->where('present', true)
            ->count();

        $parRegime = InscriptionCantine::where('actif', true)
            ->selectRaw('regime, COUNT(*) as total')
            ->groupBy('regime')
            ->pluck('total', 'regime');

        $nbAlertesStock = StockCuisine::enAlerte()->count();

        $caMois = RepasJournalier::where('present', true)
            ->whereMonth('date_repas', $today->month)
            ->whereYear('date_repas', $today->year)
            ->sum('prix_applique');

        $debut = $today->copy()->startOfWeek()->toDateString();
        $fin   = $today->copy()->endOfWeek()->toDateString();
        $menusSemaine = MenuCantine::publies()
            ->semaine($debut, $fin)
            ->orderBy('date_repas')
            ->get(['id', 'date_repas', 'plat_principal', 'prix_unitaire']);

        return $this->success([
            'date'                => $today->format('d/m/Y'),
            'menu_du_jour'        => $menuDuJour,
            'inscrits_actifs'     => $inscritsActifs,
            'presents_aujourdhui' => $presentsAujourdhui,
            'taux_presence'       => $inscritsActifs > 0
                ? round(($presentsAujourdhui / $inscritsActifs) * 100, 1) : 0,
            'par_regime'          => $parRegime,
            'alertes_stock'       => $nbAlertesStock,
            'ca_mois'             => (float) $caMois,
            'menus_semaine'       => $menusSemaine,
        ], "Tableau de bord cantine -- {$today->format('d/m/Y')}");
    }
}