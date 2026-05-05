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

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'thumbnailUrl'  => 'nullable|url',

            'categories'    => 'required|array|min:1',
            'categories.*'  => 'string',

            'formats'       => 'nullable|array',
            'formats.*'     => 'string|in:booknook,roombox,vignette,figurine,maquette,wolplay',

            'disciplines'   => 'nullable|array|max:2',
            'disciplines.*' => 'string',

            'tags'          => 'nullable|array',
            'tags.*'        => 'string|max:50',

            'sourceId'      => 'required|in:youtube,twitch',
            'youtubeId'     => 'nullable|string',
            'url'           => 'required|url',

            'author_certified' => 'required|accepted',
        ]);

        $video = DB::transaction(function () use ($validated, $request, $userId) {

            $video = Video::create([
                'creator_id'        => $userId,
                'platform'          => $request->input('provider'),
                'platform_video_id' => $request->input('youtubeId') ?? null,
                'embed_url'         => $request->input('url') ?? null,
                'thumbnailUrl'         => $request->input('thumbnailUrl') ?? null,
                'title'             => $request->input('title'),
                'description'       => $request->input('description'),
                'thumbnail_url'     => $request->input('thumbnail_url'),
                'author_certified'  => true,
                'status'            => 'published',
                'published_at'      => now(),
            ]);

            // =========================
            // 🔹 CATEGORIES
            // =========================
            $categoryIds = collect($validated['categories'])->map(function ($slug) {
                return \App\Models\Categorie::firstOrCreate(
                    ['slug' => strtolower($slug)],
                    ['name' => ucfirst($slug)]
                )->id;
            });

            $video->categories()->sync($categoryIds);

            // =========================
            // 🔹 FORMATS
            // =========================
            if (!empty($validated['formats'])) {
                $formatIds = collect($validated['formats'])->map(function ($slug) {
                    return \App\Models\Format::firstOrCreate(
                        ['slug' => strtolower($slug)],
                        ['name' => ucfirst($slug)]
                    )->id;
                });

                $video->formats()->sync($formatIds);
            }

            // =========================
            // 🔹 DISCIPLINES
            // =========================
            if (!empty($validated['disciplines'])) {
                $disciplineIds = collect($validated['disciplines'])->map(function ($slug) {
                    return \App\Models\Discipline::firstOrCreate(
                        ['slug' => strtolower($slug)],
                        ['name' => ucfirst($slug)]
                    )->id;
                });

                $video->disciplines()->sync($disciplineIds);
            }

            // =========================
            // 🔹 TAGS
            // =========================
            if (!empty($validated['tags'])) {
                $tagIds = collect($validated['tags'])->map(function ($label) {
                    return \App\Models\Tag::firstOrCreate([
                        'label' => strtolower(trim($label))
                    ])->id;
                });

                $video->tags()->sync($tagIds);
            }

            // 🔹 Promote user
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
