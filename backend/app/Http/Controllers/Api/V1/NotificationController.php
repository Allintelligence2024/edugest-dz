<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\FirebaseService;
use Illuminate\Http\{Request, JsonResponse};

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->when($request->lut, fn($q) => $q->where('est_lu', false))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        $nonLu = Notification::where('user_id', auth()->id())->where('est_lu', false)->count();

        return response()->json([
            'success' => true,
            'data'    => $notifications,
            'non_lu'  => $nonLu,
        ]);
    }

    public function marquerLu(string $id): JsonResponse
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);
        $notification->update(['est_lu' => true]);
        return response()->json(['success' => true, 'message' => 'Notification marquée comme lue']);
    }

    public function toutLire(): JsonResponse
    {
        Notification::where('user_id', auth()->id())->where('est_lu', false)
            ->update(['est_lu' => true]);
        return response()->json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }

    public function destroy(string $id): JsonResponse
    {
        Notification::where('user_id', auth()->id())->findOrFail($id)->delete();
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
            'user_id' => $validated['destinataire_id'],
            'titre'   => $validated['titre'],
            'message' => $validated['message'],
            'type'    => $validated['type'] ?? 'info',
            'lien'    => $validated['lien'] ?? null,
        ]);

        $this->sendPushNotification($notification);

        return response()->json(['success' => true, 'message' => 'Notification envoyée', 'data' => $notification], 201);
    }

    private function sendPushNotification(Notification $notification): void
    {
        try {
            $firebase = app(FirebaseService::class);
            $firebase->sendToUser($notification->user_id, [
                'title' => $notification->titre,
                'body'  => $notification->message,
            ], array_filter([
                'type'          => $notification->type,
                'notification_id' => $notification->id,
                'lien'          => $notification->lien,
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('stack')->warning(
                'Push notification non envoyée: ' . $e->getMessage()
            );
        }
    }
}
