<?php

namespace App\Services;

use App\Models\LmsCours;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class LmsCoursService
{
    public function __construct(private LmsService $lms) {}

    /**
     * @return array{paginator: LengthAwarePaginator, stats: array}
     */
    public function indexCours(Request $request): array
    {
        $paginator = LmsCours::with('enseignant:id,nom,prenom')
            ->withCount('inscriptions')
            ->when($request->filled('matiere'),  fn($q) => $q->where('matiere', $request->matiere))
            ->when($request->filled('publie'),   fn($q) => $q->where('publie', (bool) $request->publie))
            ->when($request->filled('niveau'),   fn($q) => $q->whereJsonContains('niveaux_cibles', $request->niveau))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return ['paginator' => $paginator, 'stats' => $this->lms->getDashboard()];
    }

    /**
     * @return array{cours: LmsCours}
     */
    public function storeCours(array $data): array
    {
        $v = Validator::make($data, [
            'titre'           => 'required|string|max:200',
            'description'     => 'nullable|string',
            'matiere'         => 'nullable|string|max:100',
            'niveaux_cibles'  => 'array',
            'langue'          => 'in:ar,fr,en',
            'duree_estimee'   => 'nullable|string|max:20',
            'seuil_completion'=> 'integer|min:1|max:100',
            'certificat_actif'=> 'boolean',
        ])->validate();

        /** @var array{titre: mixed, description?: mixed, matiere?: mixed, niveaux_cibles?: mixed, langue?: mixed, duree_estimee?: mixed, seuil_completion?: mixed, certificat_actif?: mixed, tenant_id: mixed, enseignant_id: mixed, publie: mixed} $attributes */
        $attributes = [
            ...$v,
            'tenant_id'      => config('tenant.current_id'),
            'enseignant_id'  => auth('api')->id(),
            'publie'         => false,
        ];

        return ['cours' => LmsCours::create($attributes)];
    }

    /**
     * @return array{cours: LmsCours}
     */
    public function showCours(string $id): array
    {
        $cours = LmsCours::with([
            'enseignant:id,nom,prenom',
            'chapitres.lecons',
            'inscriptions' => fn($q) => $q->limit(5),
        ])->findOrFail($id);

        return ['cours' => $cours];
    }

    /**
     * @return array{cours: LmsCours, message: string}|array{error: string}
     */
    public function publierCours(string $id): array
    {
        $cours = LmsCours::findOrFail($id);

        $nbLecons = $cours->chapitres()->withCount('lecons')->get()->sum('lecons_count');
        if ($nbLecons === 0) {
            return ['error' => 'Ajouter au moins 1 chapitre et 1 leçon avant de publier.'];
        }

        $cours->update([
            'publie'       => !$cours->publie,
            'nb_chapitres' => $cours->chapitres()->count(),
            'nb_lecons'    => $nbLecons,
        ]);

        return [
            'cours'   => $cours->fresh(),
            'message' => $cours->publie ? 'Cours publié ✅' : 'Cours dépublié',
        ];
    }
}
