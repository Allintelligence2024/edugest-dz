<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
                'total' => 0,
            ]);
        }

        $tenantId = $request->user()->tenant_id;
        $term = '%' . $q . '%';

        $eleves = DB::table('eleves')
            ->where('eleves.tenant_id', $tenantId)
            ->where('eleves.statut', 'actif')
            ->where(function ($query) use ($term) {
                $query->where('nom', 'ilike', $term)
                    ->orWhere('prenom', 'ilike', $term)
                    ->orWhere('numero_inscription', 'ilike', $term);
            })
            ->select('id', 'nom', 'prenom', 'numero_inscription as ref', DB::raw("'eleve' as type"))
            ->limit(10)
            ->get();

        $enseignants = DB::table('enseignants')
            ->where('enseignants.tenant_id', $tenantId)
            ->where('enseignants.statut', 'actif')
            ->where(function ($query) use ($term) {
                $query->where('enseignants.nom', 'ilike', $term)
                    ->orWhere('enseignants.prenom', 'ilike', $term)
                    ->orWhere('enseignants.matricule', 'ilike', $term);
            })
            ->leftJoin('users', 'enseignants.user_id', '=', 'users.id')
            ->select(
                'enseignants.id',
                'enseignants.nom',
                'enseignants.prenom',
                'enseignants.matricule as ref',
                DB::raw("'enseignant' as type"),
                'users.email as user_email'
            )
            ->limit(10)
            ->get();

        $matieres = DB::table('matieres')
            ->where('matieres.tenant_id', $tenantId)
            ->where('matieres.statut', 'actif')
            ->where('nom_fr', 'ilike', $term)
            ->select('id', 'nom_fr as nom', DB::raw("'' as prenom"), 'nom_fr as ref', DB::raw("'matiere' as type"))
            ->limit(5)
            ->get();

        $groupes = DB::table('groupes')
            ->where('groupes.tenant_id', $tenantId)
            ->where('groupes.statut', 'actif')
            ->where('nom', 'ilike', $term)
            ->select('id', 'nom', DB::raw("'' as prenom"), 'nom as ref', DB::raw("'groupe' as type"))
            ->limit(5)
            ->get();

        $salles = DB::table('salles')
            ->where('salles.tenant_id', $tenantId)
            ->where('salles.statut', 'disponible')
            ->where('nom', 'ilike', $term)
            ->select('id', 'nom', DB::raw("'' as prenom"), 'nom as ref', DB::raw("'salle' as type"))
            ->limit(5)
            ->get();

        $parents = DB::table('parents')
            ->where('parents.tenant_id', $tenantId)
            ->where(function ($query) use ($term) {
                $query->where('nom', 'ilike', $term)
                    ->orWhere('prenom', 'ilike', $term);
            })
            ->select('id', 'nom', 'prenom', DB::raw("'parent' as ref"), DB::raw("'parent' as type"))
            ->limit(5)
            ->get();

        $results = $eleves->concat($enseignants)
            ->concat($matieres)
            ->concat($groupes)
            ->concat($salles)
            ->concat($parents);

        $total = $eleves->count()
            + $enseignants->count()
            + $matieres->count()
            + $groupes->count()
            + $salles->count()
            + $parents->count();

        return response()->json([
            'success' => true,
            'data' => $results->values(),
            'total' => $total,
        ]);
    }
}
