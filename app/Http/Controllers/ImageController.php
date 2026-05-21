<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Etabli;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ImageController extends Controller
{
    /**
     * PATCH /api/images/renew
     * Renouvelle une URL d'image avec un TTL de 6 mois
     */
    public function renewUrl(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'entityType'  => 'required|in:atelier_post,etabli_item',
            'entityId'    => 'required|uuid',
            'imageIndex'  => 'required|integer|min:0',
            'newUrl'      => 'required|url',
        ]);

        $entityType = $validated['entityType'];
        $entityId = $validated['entityId'];
        $imageIndex = $validated['imageIndex'];
        $newUrl = $validated['newUrl'];

        // Vérifier l'accessibilité de l'URL
        try {
            $response = Http::head($newUrl);
            if (!$response->ok()) {
                return response()->json([
                    'message' => 'Image URL not accessible',
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to verify image URL',
            ], 422);
        }

        // Récupérer l'entité et vérifier l'ownership
        if ($entityType === 'atelier_post') {
            $entity = Post::findOrFail($entityId);
            if ($entity->author_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($entityType === 'etabli_item') {
            $entity = Etabli::findOrFail($entityId);
            if ($entity->creator_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Mettre à jour l'URL
        $images = $entity->images ?? [];
        if (!isset($images[$imageIndex])) {
            return response()->json([
                'message' => 'Image index out of range',
            ], 422);
        }

        $images[$imageIndex] = $newUrl;
        $entity->images = $images;

        // Mettre à jour la date d'expiration (6 mois)
        $expiresAt = now()->addMonths(6);
        
        // Stocker les expirations dans un champ JSON
        $expirations = $entity->image_expirations ?? [];
        $expirations[$imageIndex] = $expiresAt->toIso8601String();
        $entity->image_expirations = $expirations;

        $entity->save();

        return response()->json([
            'success'   => true,
            'expiresAt' => $expiresAt->toIso8601String(),
        ]);
    }
}
