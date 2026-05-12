<?php

namespace App\Http\Controllers;

use App\Models\Etabli;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EtabliController extends Controller
{
    /**
     * GET /api/etabli/items?creatorId=:id
     * Récupère les établis d'un créateur
     */
    public function index(Request $request)
    {
        $creatorId = $request->query('creatorId');
        $limit = (int) $request->query('limit', 50);
        $offset = (int) $request->query('offset', 0);

        $query = Etabli::where('creator_id', $creatorId)
            ->orderBy('position', 'asc');

        $total = $query->count();
        $items = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($etabli) => $this->formatEtabliResponse($etabli));

        return response()->json([
            'items'      => $items,
            'nextOffset' => ($offset + $limit < $total) ? $offset + $limit : null,
            'hasMore'    => $offset + $limit < $total,
        ]);
    }

    /**
     * POST /api/etabli/items
     * Crée un nouvel établi
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'images'      => 'nullable|array',
            'images.*'    => 'url',
            'status'      => 'nullable|in:wip,done',
            'isPinned'    => 'nullable|boolean',
        ]);

        // Récupérer la position suivante
        $nextPosition = Etabli::where('creator_id', $user->id)
            ->max('position') + 1;

        $etabli = Etabli::create([
            'creator_id'  => $user->id,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'images'      => $validated['images'] ?? [],
            'status'      => $validated['status'] ?? null,
            'position'    => $nextPosition,
            'is_pinned'   => $validated['isPinned'] ?? false,
        ]);

        return response()->json(
            $this->formatEtabliResponse($etabli),
            201
        );
    }

    /**
     * PATCH /api/etabli/items/:itemId
     * Modifie un établi existant
     */
    public function update(Request $request, $itemId)
    {
        $user = Auth::user();
        $etabli = Etabli::findOrFail($itemId);

        // Vérifier que l'user est le créateur
        if ($etabli->creator_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'images'      => 'nullable|array',
            'images.*'    => 'url',
            'status'      => 'nullable|in:wip,done',
            'isPinned'    => 'nullable|boolean',
        ]);

        $etabli->update([
            'title'       => $validated['title'] ?? $etabli->title,
            'description' => $validated['description'] ?? $etabli->description,
            'images'      => $validated['images'] ?? $etabli->images,
            'status'      => $validated['status'] ?? $etabli->status,
            'is_pinned'   => $validated['isPinned'] ?? $etabli->is_pinned,
        ]);

        return response()->json($this->formatEtabliResponse($etabli));
    }

    /**
     * DELETE /api/etabli/items/:itemId
     * Supprime un établi et tous les posts qui le partagent
     */
    public function destroy($itemId)
    {
        $user = Auth::user();
        $etabli = Etabli::findOrFail($itemId);

        // Vérifier que l'user est le créateur
        if ($etabli->creator_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Cascade: supprimer tous les posts Atelier qui partagent cet établi
        Post::where('type', 'shared_etabli')
            ->whereJsonContains('source_snapshot->sourceId', $itemId)
            ->delete();

        $etabli->delete();

        return response()->noContent();
    }

    /**
     * PUT /api/etabli/order
     * Réordonne les établis
     */
    public function updateOrder(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'uuid|exists:etablis,id',
        ]);

        // Vérifier que tous les établis appartiennent à l'user
        $etablis = Etabli::whereIn('id', $validated['order'])
            ->get();

        foreach ($etablis as $etabli) {
            if ($etabli->creator_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Mettre à jour les positions
        foreach ($validated['order'] as $position => $id) {
            Etabli::where('id', $id)->update(['position' => $position]);
        }

        return response()->noContent();
    }

    /**
     * Formate la réponse d'un établi pour l'API
     */
    private function formatEtabliResponse(Etabli $etabli): array
    {
        return [
            'id'          => $etabli->id,
            'creatorId'   => $etabli->creator_id,
            'title'       => $etabli->title,
            'description' => $etabli->description,
            'images'      => $etabli->images ?? [],
            'status'      => $etabli->status,
            'position'    => $etabli->position,
            'isPinned'    => $etabli->is_pinned,
            'createdAt'   => $etabli->created_at->toIso8601String(),
            'updatedAt'   => $etabli->updated_at->toIso8601String(),
        ];
    }
}
