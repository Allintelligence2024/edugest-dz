<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Billet;
use App\Models\Eleve;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BilletController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'     => 'nullable|in:retard,sortie_autorisee,convocation,entree_exceptionnelle',
            'date'     => 'nullable|date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $paginator = Billet::with('eleve:id,nom,prenom,niveau_scolaire,photo_url')
            ->when($validated['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($validated['date'] ?? null, fn($q, $d) => $q->whereDate('date_billet', $d))
            ->orderByDesc('date_billet')
            ->paginate($validated['per_page'] ?? 20);

        return $this->paginatedResponse($paginator, 'Billets récupérés');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'type'           => 'required|in:retard,sortie_autorisee,convocation,entree_exceptionnelle',
            'date_billet'    => 'nullable|date',
            'heure'          => 'nullable|date_format:H:i',
            'motif'          => 'nullable|string|max:300',
            'parent_prevenu' => 'nullable|boolean',
            'note'           => 'nullable|string|max:500',
        ]);

        $validated['date_billet'] = $validated['date_billet'] ?? today()->toDateString();
        $validated['etabli_par']  = auth()->id();

        $billet = Billet::create($validated);

        $path = $this->genererPDF($billet->load('eleve'));
        $billet->update(['fichier_url' => $path]);

        return $this->created([
            'billet'      => $billet->fresh('eleve'),
            'type_label'  => $billet->type_label,
            'fichier_url' => $path,
            'pdf_link'    => "/api/v1/billets/{$billet->id}/pdf",
        ], "{$billet->type_label} créé pour {$billet->eleve->prenom} {$billet->eleve->nom}");
    }

    public function pdf(string $id)
    {
        $billet = Billet::with('eleve')->findOrFail($id);

        if ($billet->fichier_url && Storage::disk('public')->exists($billet->fichier_url)) {
            return response()->download(storage_path('app/public/' . $billet->fichier_url));
        }

        $path = $this->genererPDF($billet);
        return response()->download(storage_path('app/public/' . $path));
    }

    public function parEleve(string $eleveId): JsonResponse
    {
        $eleve   = Eleve::findOrFail($eleveId);
        $billets = Billet::where('eleve_id', $eleveId)
            ->orderByDesc('date_billet')
            ->get();

        return $this->success([
            'eleve'   => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'billets' => $billets,
            'stats'   => [
                'retards' => $billets->where('type', 'retard')->count(),
                'sorties' => $billets->where('type', 'sortie_autorisee')->count(),
                'total'   => $billets->count(),
            ],
        ]);
    }

    private function genererPDF(Billet $billet): string
    {
        $eleve  = $billet->eleve;
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $pdf = Pdf::loadView('pdf.billet', [
            'billet' => $billet,
            'eleve'  => $eleve,
            'tenant' => $tenant,
        ])->setPaper([0, 0, 595, 300], 'landscape');

        $path = "billets/{$billet->tenant_id}/{$billet->date_billet->format('Y-m-d')}/{$billet->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        return $path;
    }
}
