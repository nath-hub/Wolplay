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
            ->map(fn($etabli) => $this->formatEtabliResponse($etabli));

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
        $etabli = Etabli::where('id', $itemId)->where('creator_id', $user->id)->first();

        // Vérifier que l'user est le créateur
        if (!$etabli) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'imageUrls'      => 'nullable|array',
            'imageUrls.*'    => 'url',
            'status'      => 'nullable|in:wip,done',
        ]);

        $etabli->update([
            'title'       => $validated['title'] ?? $etabli->title,
            'description' => $validated['description'] ?? $etabli->description,
            'images'      => $validated['imageUrls'] ?? $etabli->images,
            'status'      => $validated['status'] ?? $etabli->status, 
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
        $etabli = Etabli::where('id', $itemId)->where('creator_id', $user->id)->first();

        // Vérifier que l'user est le créateur
        if (!$etabli) {
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
     * Réordonne les établis et gère l'épinglage
     */
    public function updateOrder(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'creatorId'   => 'required|uuid',
            'orderedIds'  => 'required|array',
            'orderedIds.*' => 'uuid|exists:etablis,id',
            'pinnedId'    => 'nullable|uuid|exists:etablis,id',
        ]);

        $creatorId = $validated['creatorId'];
        $orderedIds = $validated['orderedIds'];
        $pinnedId = $validated['pinnedId'];

        // Vérifier que le créateur correspond à l'utilisateur connecté
        if ($creatorId !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Vérifier que tous les établis appartiennent à l'user
        $etablis = Etabli::whereIn('id', $orderedIds)->get();

        foreach ($etablis as $etabli) {
            if ($etabli->creator_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Réorganiser avec pinnedId en premier
        $finalOrder = $orderedIds;
        if ($pinnedId && in_array($pinnedId, $orderedIds)) {
            $finalOrder = array_filter($orderedIds, fn($id) => $id !== $pinnedId);
            array_unshift($finalOrder, $pinnedId);
        }

        // Réinitialiser tous les pinned à false, puis mettre à jour uniquement le pinnedId
        Etabli::where('creator_id', $user->id)->update(['is_pinned' => false]);

        if ($pinnedId) {
            Etabli::where('id', $pinnedId)->update(['is_pinned' => true]);
        }

        // Mettre à jour les positions
        foreach ($finalOrder as $position => $id) {
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
            'type'           => 'shared_etabli', // La valeur attendue par Zod
            'sourceSnapshot' => (object)[],      // Un objet vide au lieu de null

        ];
    }
}
