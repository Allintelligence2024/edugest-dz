<?php

namespace App\Services;

use App\Models\MenuCantine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class MenuCantineService
{
    /**
     * @return array{paginator: LengthAwarePaginator, debut: mixed, fin: mixed}
     */
    public function indexMenus(array $data): array
    {
        $validated = Validator::make($data, [
            'debut'    => 'nullable|date',
            'fin'      => 'nullable|date|after_or_equal:debut',
            'per_page' => 'nullable|integer|min:5|max:100',
        ])->validate();

        $debut = $validated['debut'] ?? today()->startOfWeek()->toDateString();
        $fin   = $validated['fin']   ?? today()->endOfWeek()->toDateString();

        $paginator = MenuCantine::whereBetween('date_repas', [$debut, $fin])
            ->orderBy('date_repas')
            ->paginate($validated['per_page'] ?? 30);

        return ['paginator' => $paginator, 'debut' => $debut, 'fin' => $fin];
    }

    /**
     * @return array{semaine_debut: string, semaine_fin: string, jours: mixed}
     */
    public function menuSemaine(Request $request): array
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

        return ['semaine_debut' => $debut, 'semaine_fin' => $fin, 'jours' => $menus];
    }

    /**
     * @return array{menu: MenuCantine, message: string}
     */
    public function storeMenu(array $data): array
    {
        $validated = Validator::make($data, [
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
        ])->validate();

        $menu = MenuCantine::create($validated);

        return [
            'menu'    => $menu,
            'message' => "Menu du {$menu->date_repas->format('d/m/Y')} cree",
        ];
    }

    /**
     * @return array{menu: MenuCantine}
     */
    public function updateMenu(string $id, array $data): array
    {
        $menu      = MenuCantine::findOrFail($id);
        $validated = Validator::make($data, [
            'plat_principal'     => 'sometimes|string|max:200',
            'accompagnement'     => 'nullable|string|max:200',
            'dessert'            => 'nullable|string|max:150',
            'prix_unitaire'      => 'sometimes|numeric|min:0',
            'nb_couverts_prevus' => 'nullable|integer|min:0',
            'publie'             => 'sometimes|boolean',
            'note'               => 'nullable|string|max:500',
        ])->validate();

        $menu->update($validated);

        return ['menu' => $menu->fresh()];
    }

    /**
     * @return array{message: string}
     */
    public function destroyMenu(string $id): array
    {
        $menu = MenuCantine::findOrFail($id);
        $menu->delete();

        return ['message' => "Menu du {$menu->date_repas->format('d/m/Y')} supprime"];
    }
}
