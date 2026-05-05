<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class VideoDisciplinesController extends Controller
{
    // ── fetchPinnedVideos ─────────────────────────────────────────────────────
    // Toutes les vidéos du créateur (son catalogue dans Mon Espace > Vidéos)

    #[OA\Get(
        path: '/api/users/{userId}/videos',
        summary: 'Récupérer toutes les vidéos d\'un créateur spécifique',
        description: 'Retourne la liste complète des vidéos appartenant à l\'utilisateur. Nécessite d\'être le propriétaire.',
        tags: ['Creator Videos'],
        security: [['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID de l\'utilisateur (UUID)',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos du créateur',
         content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Vidéos récupérées avec succès'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Interdit - Vous n\'êtes pas le propriétaire')]
    public function index(string $userId): JsonResponse
    {
        $this->authorizeOwner($userId);

        $videos = Video::with(['category', 'disciplines', 'tags'])
            ->where('creator_id', $userId)
            ->latest('published_at')
            ->get();

        return response()->json($videos);
    }

    // ── fetchFeaturedVideoIds ─────────────────────────────────────────────────
    // Retourne les IDs ordonnés des 6 slots mis en avant

    public function featuredIds(string $userId): JsonResponse
    {
        $this->authorizeOwner($userId);

        $ids = DB::table('featured_videos')
            ->where('user_id', $userId)
            ->orderBy('slot')
            ->pluck('video_id');

        return response()->json($ids);
    }

    // ── updateFeaturedVideoIds ────────────────────────────────────────────────
    // Remplace les 6 slots d'un coup (ordre = position dans le tableau)

    #[OA\Get(
        path: '/api/users/{userId}/videos/featured',
        summary: 'Récupérer les IDs des vidéos mises en vedette',
        description: 'Retourne les IDs des vidéos sélectionnées pour le profil, triés par emplacement (slot).',
        tags: ['Creator Videos'],
        security: [['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Tableau d\'IDs de vidéos',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'IDs récupérés avec succès'),
            ]
        )
    )]
    public function updateFeatured(Request $request, string $userId): JsonResponse
    {
        $this->authorizeOwner($userId);

        $request->validate([
            'ids'   => 'required|array|max:6',
            'ids.*' => ['uuid', Rule::exists('videos', 'id')->where('creator_id', $userId)],
        ]);

        $ids = $request->input('ids');

        DB::transaction(function () use ($userId, $ids) {
            DB::table('featured_videos')->where('user_id', $userId)->delete();

            foreach ($ids as $slot => $videoId) {
                DB::table('featured_videos')->insert([
                    'user_id'  => $userId,
                    'video_id' => $videoId,
                    'slot'     => $slot + 1,   // slots 1-6
                ]);
            }
        });

        return response()->json($ids);
    }

    // ── addPinnedVideo ────────────────────────────────────────────────────────
    // Ajoute une vidéo au catalogue du créateur via URL YouTube/Twitch

    #[OA\Put(
        path: '/api/users/{userId}/videos/featured',
        summary: 'Mettre à jour la sélection de vidéos en vedette',
        description: 'Remplace la sélection actuelle par une nouvelle liste (max 6 vidéos). Les vidéos doivent appartenir à l\'utilisateur.',
        tags: ['Creator Videos'],
        security: [['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        description: 'Liste ordonnée d\'IDs de vidéos (max 6)',
                        maxItems: 6,
                        items: new OA\Items(type: 'string', format: 'uuid')
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Sélection mise à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Sélection mise à jour avec succès')
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Erreur de validation (ex: vidéo n\'appartient pas au créateur)')]
    public function store(Request $request, string $userId): JsonResponse
    {
        $this->authorizeOwner($userId);

        $request->validate([
            'platform'         => 'required|in:youtube,twitch',
            'platform_video_id' => 'required|string|max:255',
            'embed_url'        => 'required|url',
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'thumbnail_url'    => 'nullable|url',
            'category_id'      => 'required|uuid|exists:categories,id',
            'format'           => 'nullable|in:booknook,roombox,vignette,figurine,maquette,wolplay',
            'author_certified' => 'required|accepted',   // doit être true (case cochée §2.2)
            'discipline_ids'   => 'nullable|array|max:2',
            'discipline_ids.*' => 'uuid|exists:disciplines,id',
            'tag_labels'       => 'nullable|array',
            'tag_labels.*'     => 'string|max:50',
        ]);

        $video = DB::transaction(function () use ($request, $userId) {

            $video = Video::create([
                'creator_id'        => $userId,
                'platform'          => $request->input('platform'),
                'platform_video_id' => $request->input('platform_video_id'),
                'embed_url'         => $request->input('embed_url'),
                'title'             => $request->input('title'),
                'description'       => $request->input('description'),
                'thumbnail_url'     => $request->input('thumbnail_url'),
                'category_id'       => $request->input('category_id'),
                'format'            => $request->input('format'),
                'author_certified'  => true,
                'status'            => 'published',
                'published_at'      => now(),
            ]);

            // Disciplines (0-2 selon catégorie)
            if ($request->filled('discipline_ids')) {
                $video->disciplines()->sync($request->input('discipline_ids'));
            }

            // Tags libres — créer si inexistants
            if ($request->filled('tag_labels')) {
                $tagIds = collect($request->input('tag_labels'))->map(function ($label) {
                    return \App\Models\Tag::firstOrCreate(['label' => $label])->id;
                });
                $video->tags()->sync($tagIds);
            }

            // Promouvoir le user en créateur s'il ne l'est pas encore
            $user = \App\Models\User::find($userId);
            if ($user->role === 'member') {
                $user->update(['role' => 'creator']);
            }

            return $video;
        });

        return response()->json($video->load(['category', 'disciplines', 'tags']), 201);
    }

    // ── deletePinnedVideo ─────────────────────────────────────────────────────
    #[OA\Delete(
        path: '/api/users/{userId}/videos/{videoId}',
        summary: 'Supprimer une vidéo',
        description: 'Supprime une vidéo appartenant à l\'utilisateur. Cela retire également la vidéo de sa sélection "En vedette" si elle y était présente.',
        tags: ['Creator Videos'],
        security: [['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID de l\'utilisateur propriétaire (UUID)',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'videoId',
                in: 'path',
                required: true,
                description: 'ID de la vidéo à supprimer (UUID)',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ]
    )]
    #[OA\Response(
        response: 204,
        description: 'Vidéo supprimée avec succès (pas de contenu retourné)'
    )]
    #[OA\Response(
        response: 403,
        description: 'Interdit - Vous n\'êtes pas le propriétaire de cette ressource'
    )]
    #[OA\Response(
        response: 404,
        description: 'Vidéo non trouvée ou n\'appartient pas à cet utilisateur'
    )]
    public function destroy(string $userId, string $videoId): JsonResponse
    {
        $this->authorizeOwner($userId);

        $video = Video::where('creator_id', $userId)->findOrFail($videoId);

        DB::transaction(function () use ($userId, $videoId, $video) {
            // Retirer des slots mis en avant si présent
            DB::table('featured_videos')
                ->where('user_id', $userId)
                ->where('video_id', $videoId)
                ->delete();

            $video->delete(); // SoftDelete
        });

        return response()->json(null, 204);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function authorizeOwner(string $userId): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->id === $userId || $user->isAdmin()),
            403,
            'Action non autorisée.'
        );
    }
}
