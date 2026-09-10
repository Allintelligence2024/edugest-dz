<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\InscriptionCantine;
use App\Models\MenuCantine;
use App\Models\RepasJournalier;
use App\Models\StockCuisine;
use App\Services\InscriptionCantineService;
use App\Services\MenuCantineService;
use App\Services\PointageCantineService;
use App\Services\StockCuisineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CantineController extends BaseApiController
{
    public function __construct(
        private readonly MenuCantineService $menus,
        private readonly InscriptionCantineService $inscriptions,
        private readonly PointageCantineService $pointages,
        private readonly StockCuisineService $stock,
    ) {}

    public function indexMenus(Request $request): JsonResponse
    {
        $result = $this->menus->indexMenus($request->all());

        return $this->paginatedResponse(
            $result['paginator'],
            "Menus du {$result['debut']} au {$result['fin']}",
            ['periode' => ['debut' => $result['debut'], 'fin' => $result['fin']]]
        );
    }

    public function menuSemaine(Request $request): JsonResponse
    {
        $result = $this->menus->menuSemaine($request);

        return $this->success([
            'semaine_debut' => $result['semaine_debut'],
            'semaine_fin'   => $result['semaine_fin'],
            'jours'         => $result['jours'],
        ], "Menu semaine du {$result['semaine_debut']} au {$result['semaine_fin']}");
    }

    public function storeMenu(Request $request): JsonResponse
    {
        $result = $this->menus->storeMenu($request->all());

        return $this->created($result['menu'], $result['message']);
    }

    public function updateMenu(Request $request, string $id): JsonResponse
    {
        $result = $this->menus->updateMenu($id, $request->all());

        return $this->success($result['menu'], 'Menu mis a jour');
    }

    public function destroyMenu(string $id): JsonResponse
    {
        $result = $this->menus->destroyMenu($id);

        return $this->success(null, $result['message']);
    }

    public function indexInscriptions(Request $request): JsonResponse
    {
        $result = $this->inscriptions->indexInscriptions($request->all());

        return $this->paginatedResponse($result['paginator'], 'Inscriptions cantine', ['stats' => $result['stats']]);
    }

    public function inscrireEleve(Request $request): JsonResponse
    {
        $result = $this->inscriptions->inscrireEleve($request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->created([
            'inscription'   => $result['inscription'],
            'eleve'         => $result['eleve'],
            'regime_label'  => $result['regime_label'],
        ], $result['message']);
    }

    public function updateInscription(Request $request, string $id): JsonResponse
    {
        $result = $this->inscriptions->updateInscription($id, $request->all());

        return $this->success($result['inscription'], 'Inscription mise a jour');
    }

    public function desinscrireEleve(string $id): JsonResponse
    {
        $result = $this->inscriptions->desinscrireEleve($id);

        return $this->success(null, $result['message']);
    }

    public function pointer(Request $request): JsonResponse
    {
        $result = $this->pointages->pointer($request->all());

        return $this->success([
            'date'        => $result['date'],
            'type_repas'  => $result['type_repas'],
            'enregistres' => $result['enregistres'],
            'presents'    => $result['presents'],
            'absents'     => $result['absents'],
        ], $result['message']);
    }

    public function pointageDate(string $date): JsonResponse
    {
        return $this->success($this->pointages->pointageDate($date));
    }

    public function indexStock(Request $request): JsonResponse
    {
        $result = $this->stock->indexStock($request);

        return $this->success([
            'articles'    => $result['articles'],
            'nb_alertes'  => $result['nb_alertes'],
            'nb_articles' => $result['nb_articles'],
        ], 'Stock cuisine recupere');
    }

    public function storeStock(Request $request): JsonResponse
    {
        $result = $this->stock->storeStock($request->all());

        return $this->created($result['article'], $result['message']);
    }

    public function mouvementStock(Request $request, string $id): JsonResponse
    {
        $result = $this->stock->mouvementStock($id, $request->all());

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['code'], $result['status']);
        }

        return $this->success([
            'article'       => $result['article'],
            'mouvement'     => $result['mouvement'],
            'quantite'      => $result['quantite'],
            'nouveau_stock' => $result['nouveau_stock'],
            'en_alerte'     => $result['en_alerte'],
        ], "Stock {$result['article_nom']} mis a jour : {$result['nouveau_stock']} {$result['article_unite']}");
    }

    public function alertesStock(): JsonResponse
    {
        $result = $this->stock->alertesStock();

        return $this->success([
            'alertes'    => $result['alertes'],
            'nb_alertes' => $result['nb_alertes'],
        ], $result['message']);
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
