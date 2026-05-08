<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
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
        security: [['bearerAuth' => []]],
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

        $videos = Video::with(['category', 'disciplines', 'tags', 'formats'])
            ->where('creator_id', $userId)
            ->latest('published_at')
            ->get();

        return response()->json(VideoResource::collection($videos));
    }

    // ── fetchPinnedVideos public ───────────────────────────────────────────────
    // Accessible sans authentification pour consulter les vidéos épinglées d'un profil public

    #[OA\Get(
        path: '/api/videos/pinned',
        summary: 'Récupérer les vidéos épinglées d\'un créateur',
        description: 'Retourne la liste des vidéos épinglées d\'un utilisateur public.',
        tags: ['Creator Videos'],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'query',
                required: true,
                description: 'ID de l\'utilisateur propriétaire',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos épinglées',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object'
            )
        )
    )]
    public function publicPinned(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'userId' => 'required|uuid|exists:users,id',
        ]);

        $videos = Video::with(['category', 'disciplines', 'tags', 'formats'])
            ->where('creator_id', $validated['userId'])
            ->latest('published_at')
            ->get();

        return response()->json(VideoResource::collection($videos));
    }

    // ── fetchFeaturedVideoIds ─────────────────────────────────────────────────
    // Retourne les IDs ordonnés des 6 slots mis en avant
    #[OA\Get(
        path: '/api/users/{userId}/videos/featured',
        summary: 'Récupérer les IDs des vidéos mises en avant par le créateur',
        description: 'Retourne une liste ordonnée d\'IDs (UUID) correspondant aux vidéos sélectionnées pour le profil du créateur.',
        tags: ['Creator Videos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                description: 'ID de l\'utilisateur (UUID)',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Tableau d\'IDs de vidéos récupéré avec succès',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'string', format: 'uuid'),
            example: [
                "550e8400-e29b-41d4-a716-446655440000",
                "678e8400-e29b-41d4-a716-446655441111"
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié'
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès refusé (vous n\'êtes pas le propriétaire de ce profil)'
    )]
    public function featuredIds(string $userId): JsonResponse
    {
        // $this->authorizeOwner($userId);

        $ids = DB::table('featured_videos')
            ->where('user_id', $userId)
            ->orderBy('slot')
            ->pluck('video_id');

        return response()->json($ids);
    }

    // ── updateFeaturedVideoIds ────────────────────────────────────────────────
    // Remplace les 6 slots d'un coup (ordre = position dans le tableau)

    #[OA\PUT(
        path: '/api/users/{userId}/videos/featured',
        summary: 'Mettre à jour les IDs des vidéos mises en vedette',
        description: 'Remplace les IDs des vidéos sélectionnées pour le profil, triés par emplacement (slot).',
        tags: ['Creator Videos'],
        security: [['bearerAuth' => []]],
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

    #[OA\Post(
        path: '/api/videos/pinned',
        summary: 'Ajouter une vidéo au catalogue',
        description: 'Ajoute une nouvelle vidéo au catalogue du créateur.',
        security: [['bearerAuth' => []]],
        tags: ['Creator Videos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'userId',
                        type: 'string',
                        format: 'uuid',
                        description: 'ID de l\'utilisateur propriétaire'
                    ),
                    new OA\Property(
                        property: 'title',
                        type: 'string',
                        description: 'Titre de la vidéo'
                    ),
                    new OA\Property(
                        property: 'description',
                        type: 'string',
                        description: 'Description de la vidéo'
                    ),
                    new OA\Property(
                        property: 'thumbnailUrl',
                        type: 'string',
                        format: 'url',
                        description: 'URL de la miniature'
                    ),
                    new OA\Property(
                        property: 'categories',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Catégories de la vidéo'
                    ),
                    new OA\Property(
                        property: 'formats',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Formats de la vidéo'
                    ),
                    new OA\Property(
                        property: 'disciplines',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Disciplines de la vidéo'
                    ),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Tags de la vidéo'
                    ),
                    new OA\Property(
                        property: 'sourceId',
                        type: 'string',
                        description: 'ID source de la vidéo'
                    ),
                    new OA\Property(
                        property: 'youtubeId',
                        type: 'string',
                        description: 'ID YouTube'
                    ),
                    new OA\Property(
                        property: 'url',
                        type: 'string',
                        format: 'url',
                        description: 'URL de la vidéo'
                    ),
                    new OA\Property(
                        property: 'provider',
                        type: 'string',
                        description: 'Plateforme (YouTube, etc.)'
                    )
                ],
                required: ['userId', 'title', 'categories', 'sourceId', 'url']
            )
        )
    )]

    #[OA\Response(
        response: 201,
        description: 'Vidéo créée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'thumbnail_url', type: 'string'),
                new OA\Property(property: 'embed_url', type: 'string'),
                new OA\Property(property: 'platform', type: 'string'),
                new OA\Property(property: 'platform_video_id', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'published_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'category', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'disciplines', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'object'))
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Interdit - Vous n\'êtes pas le propriétaire')]
    #[OA\Response(response: 422, description: 'Erreur de validation')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'userId'        => 'required|uuid|exists:users,id',
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

            'sourceId'      => 'required',
            'youtubeId'     => 'nullable|string',
            'url'           => 'required|url',

            'author_certified' => 'nullable',
            'provider'      => 'nullable|string',
        ]);

        $userId = $validated['userId'];

        $this->authorizeOwner($userId);

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
                'is_wolplay_pick'  => true,
                'is_featured'      => true,
                'status'            => 'published',
                'published_at'      => now(),
            ]);

            // =========================
            // 🔹 CATEGORIES
            // =========================
            $categoryIds = collect($validated['categories'])->map(function ($slug) {
                return \App\Models\Categorie::firstOrCreate(
                    ['name' => ucfirst($slug)]
                )->id;
            });

            $video->category()->sync($categoryIds);

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

                // 1. Lier à la vidéo
                $video->disciplines()->sync($disciplineIds);

                // 2. Lier aussi au user (creator)
                $video->creator->disciplines()->syncWithoutDetaching($disciplineIds);
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
        security: [['bearerAuth' => []]],
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
