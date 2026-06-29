<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Conversation, Message, User};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $user = auth()->user();

        $conversations = Conversation::where('tenant_id', config('tenant.current_id'))
            ->whereJsonContains('participants', $user->id)
            ->orderByDesc('last_message_at')
            ->paginate($request->per_page ?? 20);

        $nonLu = $conversations->filter(fn($c) =>
            !in_array($user->id, $c->lu_par ?? [])
        )->count();

        return response()->json([
            'success' => true,
            'data'    => $conversations->items(),
            'meta'    => ['total' => $conversations->total(), 'non_lu' => $nonLu],
        ]);
    }

    public function creerConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sujet'       => 'nullable|string|max:200',
            'participants' => 'required|array|min:1',
            'participants.*' => 'uuid|exists:users,id',
            'message'     => 'required|string',
        ]);

        $user = auth()->user();
        $participants = array_unique([$user->id, ...$validated['participants']]);

        $conversation = DB::transaction(function () use ($validated, $participants, $user) {
            $conv = Conversation::create([
                'tenant_id'      => config('tenant.current_id'),
                'sujet'          => $validated['sujet'] ?? null,
                'participants'   => $participants,
                'lu_par'         => [$user->id],
                'last_message_at' => now(),
            ]);

            Message::create([
                'conversation_id' => $conv->id,
                'expediteur_id'   => $user->id,
                'message'         => $validated['message'],
                'type_message'    => 'texte',
            ]);

            return $conv;
        });

        return response()->json([
            'success' => true,
            'data'    => $conversation->load('messages.expediteur'),
            'message' => 'Conversation créée',
        ], 201);
    }

    public function conversation(string $id): JsonResponse
    {
        $conv = Conversation::where('tenant_id', config('tenant.current_id'))
            ->findOrFail($id);

        $user = auth()->user();
        if (!in_array($user->id, $conv->participants ?? [])) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Vous n\'êtes pas participant à cette conversation'],
            ], 403);
        }

        $messages = Message::where('conversation_id', $conv->id)
            ->with('expediteur:id,nom,prenom,photo_url')
            ->orderBy('created_at')
            ->paginate(50);

        $luPar = $conv->lu_par ?? [];
        if (!in_array($user->id, $luPar)) {
            $luPar[] = $user->id;
            $conv->update(['lu_par' => $luPar]);
        }

        return response()->json([
            'success' => true,
            'data'    => ['conversation' => $conv, 'messages' => $messages->items()],
            'meta'    => ['total' => $messages->total()],
        ]);
    }

    public function envoyer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'message'     => 'required_without:fichier|string',
            'fichier'     => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            'type_message' => 'sometimes|in:texte,fichier,image',
        ]);

        $conv = Conversation::where('tenant_id', config('tenant.current_id'))
            ->findOrFail($id);

        $user = auth()->user();
        if (!in_array($user->id, $conv->participants ?? [])) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès refusé']], 403);
        }

        $data = [
            'conversation_id' => $conv->id,
            'expediteur_id'   => $user->id,
            'message'         => $validated['message'] ?? null,
            'type_message'    => $validated['type_message'] ?? 'texte',
        ];

        if ($request->hasFile('fichier')) {
            $path = $request->file('fichier')->store(
                "messages/{$conv->tenant_id}/{$conv->id}", 'public'
            );
            $data['fichier_url'] = $path;
            $data['fichier_nom'] = $request->file('fichier')->getClientOriginalName();
            $data['type_message'] = in_array($request->file('fichier')->getClientOriginalExtension(), ['jpg','jpeg','png'])
                ? 'image' : 'fichier';
        }

        $message = Message::create($data);
        $conv->update(['last_message_at' => now(), 'lu_par' => [$user->id]]);

        return response()->json([
            'success' => true,
            'data'    => $message->load('expediteur:id,nom,prenom'),
            'message' => 'Message envoyé',
        ], 201);
    }

    public function marquerLu(Request $request, string $id): JsonResponse
    {
        $conv = Conversation::where('tenant_id', config('tenant.current_id'))
            ->findOrFail($id);

        $user = auth()->user();
        $luPar = $conv->lu_par ?? [];
        if (!in_array($user->id, $luPar)) {
            $luPar[] = $user->id;
            $conv->update(['lu_par' => $luPar]);
        }

        return response()->json(['success' => true, 'message' => 'Conversation marquée comme lue']);
    }
}
