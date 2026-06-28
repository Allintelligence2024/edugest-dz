<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('audit.read');

        $query = Activity::where('tenant_id', config('tenant.current_id'));

        if ($request->user_id) {
            $query->where('causer_id', $request->user_id);
        }

        if ($request->action) {
            $query->where('description', 'LIKE', "%{$request->action}%");
        }

        if ($request->table) {
            $query->where('subject_type', 'LIKE', "%{$request->table}%");
        }

        if ($request->date_debut) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }

        if ($request->date_fin) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        $logs = $query->with('causer:id,nom,prenom,email')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 30);

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'total' => $logs->total(),
                'page'  => $logs->currentPage(),
            ],
        ]);
    }

    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        $this->authorize('audit.read');

        $log = Activity::where('tenant_id', config('tenant.current_id'))
            ->with('causer:id,nom,prenom,email')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $log,
        ]);
    }
}
