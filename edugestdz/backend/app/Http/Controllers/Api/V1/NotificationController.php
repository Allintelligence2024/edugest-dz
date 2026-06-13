<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\{Request, JsonResponse};

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('destinataire_id', auth()->id())
            ->when($request->lut, fn($q) => $q->whereNull('lu_at'))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        $nonLu = Notification::where('destinataire_id', auth()->id())->whereNull('lu_at')->count();

        return response()->json([
            'success' => true,
            'data'    => $notifications,
            'non_lu'  => $nonLu,
        ]);
    }

    public function marquerLu(string $id): JsonResponse
    {
        $notification = Notification::where('destinataire_id', auth()->id())->findOrFail($id);
        $notification->update(['lu_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Notification marquée comme lue']);
    }

    public function toutLire(): JsonResponse
    {
        Notification::where('destinataire_id', auth()->id())->whereNull('lu_at')
            ->update(['lu_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }

    public function destroy(string $id): JsonResponse
    {
        Notification::where('destinataire_id', auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Notification supprimée']);
    }

    public function envoyer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destinataire_id' => 'required|uuid|exists:users,id',
            'titre'           => 'required|string|max:200',
            'message'         => 'required|string',
            'type'            => 'sometimes|in:info,alerte,rappel,relance',
            'lien'            => 'nullable|string|max:500',
        ]);

        $notification = Notification::create([
            'destinataire_id' => $validated['destinataire_id'],
            'titre'           => $validated['titre'],
            'message'         => $validated['message'],
            'type'            => $validated['type'] ?? 'info',
            'lien'            => $validated['lien'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Notification envoyée', 'data' => $notification], 201);
    }
}
