<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\{Request, JsonResponse};

class NoteController extends Controller
{
    public function update(Request $request, string $id): JsonResponse
    {
        $note = Note::findOrFail($id);
        $validated = $request->validate([
            'valeur'   => 'required|numeric|min:0|max:20',
            'bareme'   => 'nullable|numeric|min:0',
            'commentaire' => 'nullable|string|max:500',
        ]);

        $note->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note mise à jour',
            'data'    => $note->fresh(['evaluation', 'inscription.eleve']),
        ]);
    }
}
