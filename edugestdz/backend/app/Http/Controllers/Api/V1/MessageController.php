<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{User, Message};
use Illuminate\Http\{Request, JsonResponse};

class MessageController extends Controller
{
    public function conversations(): JsonResponse
    {
        $userId = auth()->id();

        $conversations = Message::where('expediteur_id', $userId)
            ->orWhere('destinataire_id', $userId)
            ->selectRaw('CASE WHEN expediteur_id = ? THEN destinataire_id ELSE expediteur_id END AS autre_user_id', [$userId])
            ->selectRaw('MAX(created_at) as dernier_message')
            ->selectRaw('(SELECT message FROM messages m2 WHERE (m2.expediteur_id = messages.expediteur_id AND m2.destinataire_id = messages.destinataire_id) OR (m2.expediteur_id = messages.destinataire_id AND m2.destinataire_id = messages.expediteur_id) ORDER BY created_at DESC LIMIT 1) as dernier_texte')
            ->groupBy('autre_user_id')
            ->orderBy('dernier_message', 'desc')
            ->get();

        $conversations->load(['autreUser' => fn($q) => $q->select('id', 'nom', 'prenom', 'email')]);

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    public function creerConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destinataire_id' => 'required|uuid|exists:users,id',
            'message'         => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'expediteur_id'  => auth()->id(),
            'destinataire_id'=> $validated['destinataire_id'],
            'message'        => $validated['message'],
        ]);

        return response()->json(['success' => true, 'message' => 'Message envoyé', 'data' => $message->load('expediteur:id,nom,prenom')], 201);
    }

    public function conversation(string $id, Request $request): JsonResponse
    {
        $userId = auth()->id();

        $messages = Message::where(function($q) use ($userId, $id) {
                $q->where('expediteur_id', $userId)->where('destinataire_id', $id);
            })->orWhere(function($q) use ($userId, $id) {
                $q->where('expediteur_id', $id)->where('destinataire_id', $userId);
            })
            ->with('expediteur:id,nom,prenom')
            ->orderBy('created_at')
            ->paginate($request->per_page ?? 50);

        Message::where('expediteur_id', $id)
            ->where('destinataire_id', $userId)
            ->whereNull('lu_at')
            ->update(['lu_at' => now()]);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function envoyer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'expediteur_id'  => auth()->id(),
            'destinataire_id'=> $id,
            'message'        => $validated['message'],
        ]);

        return response()->json(['success' => true, 'message' => 'Message envoyé', 'data' => $message->load('expediteur:id,nom,prenom')], 201);
    }
}
