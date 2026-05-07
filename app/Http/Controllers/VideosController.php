<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VideosController extends Controller
{
    #[OA\Get(
        path: '/api/videos/feed',
        summary: 'Récupérer le flux de vidéos (Feed)',
        description: 'Retourne une liste de vidéos filtrées par contexte (global, tutoriels, abonnements, etc.).',
        tags: ['Videos'],
        parameters: [
            new OA\Parameter(
                name: 'context',
                in: 'query',
                description: 'Le contexte de filtrage du flux',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['global', 'creator', 'tutorials', 'collections', 'wolplays', 'following'],
                    default: 'global'
                )
            ),
            new OA\Parameter(
                name: 'creatorId',
                in: 'query',
                description: 'ID du créateur (requis si context=creator)',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Nombre de vidéos à récupérer',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50, default: 20)
            ),
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                description: 'Décalage pour la pagination',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0, default: 0)
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste de vidéos récupérée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Nous vous avons envoyé par courriel le lien de réinitialisation.')
            ]
        )
    )]
    public function feed(Request $request): JsonResponse
    {
        $request->validate([
            'context'     => 'sometimes|string|in:global,creator,tutorials,collections,wolplays,following',
            'creatorId'   => 'sometimes|uuid',
            'creator_id'  => 'sometimes|uuid',
            'limit'       => 'sometimes|integer|min:1|max:50',
            'offset'      => 'sometimes|integer|min:0',
        ]);

        $context   = $request->input('context', 'global');
        $creatorId = $request->input('creatorId', $request->input('creator_id'));
        $limit     = $request->integer('limit', 20);
        $offset    = $request->integer('offset', 0);

        $query = Video::with(['creator', 'category', 'disciplines', 'tags'])
            ->published();

        match ($context) {
            'tutorials'   => $query->byCategory('Tutorials'),
            'collections' => $query->byCategory('Collections'),
            'wolplays'    => $query->byCategory('Wolplays'),
            'creator'     => $query->where('creator_id', $creatorId),
            'following'   => $query->whereIn('creator_id', function ($sub) use ($request) {
                $sub->select('followed_id')
                    ->from('follows')
                    ->where('follower_id', $request->user()?->id);
            }),
            default => $query, // global
        };

        $videos = $query->latest('published_at')->skip($offset)->take($limit)->get();

        return response()->json($videos);
    }




    #[OA\Get(
        path: '/api/next/videos',
        summary: 'Récupérer la vidéo suivante',
        description: 'Retourne la vidéo publiée juste après la vidéo actuelle selon le contexte.',
        tags: ['Videos'],
        parameters: [
            new OA\Parameter(
                name: 'current_video_id',
                in: 'query',
                description: 'ID de la vidéo en cours de lecture',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'context',
                in: 'query',
                description: 'Contexte de lecture (ex: creator)',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'creator_id',
                in: 'query',
                description: 'ID du créateur (si context=creator)',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Vidéo suivante trouvée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Vidéo suivante trouvée')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Vidéo actuelle non trouvée'
    )]
    public function next(Request $request, string $currentVideoId): JsonResponse
    {
        $request->validate([
            'context'    => 'sometimes|string',
            'creator_id' => 'sometimes|uuid',
        ]);

        $current = Video::findOrFail($currentVideoId);
        $context = $request->input('context', 'global');

        $query = Video::with(['creator', 'category'])
            ->published()
            ->where('id', '!=', $currentVideoId)
            ->where('published_at', '<=', $current->published_at);

        if ($context === 'creator') {
            $query->where('creator_id', $request->input('creator_id', $current->creator_id));
        }

        $next = $query->latest('published_at')->first();

        return response()->json($next);
    }




    #[OA\Get(
        path: '/api/featured/videos',
        summary: 'Récupérer la vidéo à la une (aléatoire)',
        description: 'Retourne une vidéo unique marquée "wolplayPick" de manière aléatoire.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Vidéo à la une récupérée',

        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Vidéo à la une récupérée')
            ]
        )
    )]
    public function featured(Request $request): JsonResponse
    {
        $video = Video::with(['creator', 'category', 'disciplines'])
            ->published()
            ->wolplayPick()
            ->inRandomOrder()
            ->first();

                // 🔥 sécurité obligatoire
    if (!$video) {
        return response()->json(null, 200);
    }

        return response()->json([
            'id'         => $video->id,
            'youtubeId'  => (string) $video->youtube_id ?? '',
            'videoTitle' => (string) $video->title ?? '',

            'creator' => [
                'id'     => $video->creator->id ?? null,
                'pseudo' => $video->creator->pseudo ?? '',
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/home/showcase',
        summary: 'Vitrines de la page d\'accueil (Premium priority)',
        description: 'Retourne 5 vidéos récentes. Priorise les créateurs ayant un plan Premium, puis complète avec les autres.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos vitrines',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos vitrines récupérée')
            ]
        )
    )]
    public function homeShowcase(): JsonResponse
    {
        $videos = Video::with(['creator', 'category', 'disciplines'])
            ->published()
            ->whereHas('creator', fn($q) => $q->where('plan', 'premium'))
            ->latest('published_at')
            ->limit(5)
            ->get();

        // Compléter avec non-premium si pas assez
        if ($videos->count() < 4) {
            $ids  = $videos->pluck('id');
            $more = Video::with(['creator', 'category', 'disciplines'])
                ->published()
                ->whereNotIn('id', $ids)
                ->latest('published_at')
                ->limit(5 - $videos->count())
                ->get();

            $videos = $videos->merge($more);
        }

        return response()->json($videos);
    }

    #[OA\Get(
        path: '/api/home/collection',
        summary: 'Sélections éditoriales (Collections)',
        description: 'Retourne les 6 dernières vidéos de la catégorie "Collections" marquées "wolplayPick".',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des collections éditoriales',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des collections éditoriales récupérée')
            ]
        )


    )]
    public function homeCollection(): JsonResponse
    {
        $videos = Video::with(['creator', 'category'])
            ->published()
            ->wolplayPick()
            ->byCategory('Collections')
            ->latest('published_at')
            ->limit(6)
            ->get();

        return response()->json($videos);
    }





    #[OA\Get(
        path: '/api/home/creators',
        summary: 'Créateurs à la une (Premium)',
        description: 'Retourne 6 créateurs premium sélectionnés aléatoirement.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des créateurs à la une',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des créateurs à la une récupérée')
            ]

        )
    )]
    public function homeCreators(): JsonResponse
    {
        $creators = User::with(['disciplines' => fn($q) => $q->limit(3), 'socialLinks'])
            ->where('plan', 'premium')
            ->where('role', 'creator')
            ->whereHas('publishedVideos')
            ->withCount('publishedVideos as video_count')
            ->withCount('followers')
            ->inRandomOrder()
            ->limit(6)
            ->get();

        return response()->json($creators);
    }





    #[OA\Get(
        path: '/api/wolplay/videos',
        summary: 'Vidéos Wolplay',
        description: 'Retourne les vidéos de la catégorie "Wolplays" avec des filtres optionnels.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos Wolplay',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos Wolplay récupérée')
            ]
        )
    )]
    public function wolplayVideos(Request $request): JsonResponse
    {
        $request->validate([
            'discipline' => 'sometimes|string',
            'format'     => 'sometimes|string',
            'limit'      => 'sometimes|integer|min:1|max:50',
            'offset'     => 'sometimes|integer|min:0',
        ]);

        $query = Video::with(['creator', 'disciplines', 'tags'])
            ->published()
            ->byCategory('Wolplays');

        if ($request->filled('discipline')) {
            $query->whereHas(
                'disciplines',
                fn($q) =>
                $q->where('slug', $request->input('discipline'))
            );
        }

        if ($request->filled('format')) {
            $query->where('format', $request->input('format'));
        }

        $videos = $query
            ->latest('published_at')
            ->skip($request->integer('offset', 0))
            ->take($request->integer('limit', 20))
            ->get();

        return response()->json($videos);
    }

    #[OA\Get(
        path: '/api/wolplay/spotlight',
        summary: 'Vidéos Wolplay en vedette',
        description: 'Retourne 4 vidéos Wolplay sélectionnées aléatoirement.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos Wolplay en vedette',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos Wolplay en vedette récupérée')
            ]
        )
    )]

    public function wolplaySpotlight(Request $request): JsonResponse
    {
        $videos = Video::with(['creator', 'disciplines'])
            ->published()
            ->wolplayPick()
            ->byCategory('Wolplays')
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return response()->json($videos);
    }



    // ── fetchTutorialVideos ───────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/videos/tutorial',
        summary: 'Vidéos Tutoriels',
        description: 'Retourne les vidéos de la catégorie "Tutoriels" avec des filtres optionnels.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos Tutoriels',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos Tutoriels récupérée')
            ]
        )
    )]
    public function tutorialVideos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discipline' => ['sometimes', 'string'],
            'limit'      => ['sometimes', 'integer', 'min:1', 'max:50'],
            'offset'     => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit  = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;

        $query = Video::query()
            ->with(['creator', 'disciplines', 'tags'])
            ->published()
            ->byCategory('Tutorials');

        // 🔎 Filtre discipline
        if (!empty($validated['discipline'])) {
            $query->whereHas('disciplines', function ($q) use ($validated) {
                $q->where('slug', $validated['discipline']);
            });
        }

        $videos = $query
            ->latest('published_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json(
            $videos
        );
    }

    // ── fetchTutorialSpotlight ────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/tutorials/spotlight',
        summary: 'Vidéos Tutoriels en vedette',
        description: 'Retourne 4 vidéos Tutoriels sélectionnées aléatoirement.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos Tutoriels en vedette',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos Tutoriels en vedette récupérée')
            ]
        )
    )]
    public function tutorialSpotlight(): JsonResponse
    {
        $videos = Video::with(['creator', 'disciplines'])
            ->published()
            ->wolplayPick()
            ->byCategory('Tutorials')
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return response()->json($videos);
    }

    // ── fetchCollectionVideos ─────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/collections/videos',
        summary: 'Vidéos Collections',
        description: 'Retourne les vidéos de la catégorie "Collections" avec des filtres optionnels.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos Collections',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos Collections récupérée')
            ]
        )
    )]
    public function collectionVideos(Request $request): JsonResponse
    {
        $request->validate([
            'limit'  => 'sometimes|integer|min:1|max:50',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $videos = Video::with(['creator'])
            ->published()
            ->byCategory('Collections')
            ->latest('published_at')
            ->skip($request->integer('offset', 0))
            ->take($request->integer('limit', 20))
            ->get();

        return response()->json($videos);
    }

    // ── fetchCollectionSpotlights ─────────────────────────────────────────────
    // { proSpotlight: Video[], premiumSpotlight: Video[] }

    #[OA\Get(
        path: '/api/collections/spotlights',
        summary: 'Vidéos Collections en vedette',
        description: 'Retourne 6 vidéos Collections sélectionnées aléatoirement, 3 pour chaque plan (pro et premium).',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos Collections en vedette',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos Collections en vedette récupérée')
            ]
        )
    )]
    public function collectionSpotlights(): JsonResponse
    {
        $proSpotlight = Video::with(['creator'])
            ->published()
            ->byCategory('Collections')
            ->whereHas('creator', fn($q) => $q->where('plan', 'pro'))
            ->inRandomOrder()
            ->limit(3)
            ->get();

        $premiumSpotlight = Video::with(['creator'])
            ->published()
            ->byCategory('Collections')
            ->whereHas('creator', fn($q) => $q->where('plan', 'premium'))
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return response()->json([
            'proSpotlight'     => $proSpotlight,
            'premiumSpotlight' => $premiumSpotlight,
        ]);
    }

    // ── fetchVideo (détail) ───────────────────────────────────────────────────
    #[OA\Get(
        path: '/api/videos/{videoId}',
        summary: 'Détail d\'une vidéo',
        description: 'Retourne les détails d\'une vidéo spécifique.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Détails de la vidéo',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Détails de la vidéo récupérés')
            ]
        )
    )]
    public function show(string $videoId): JsonResponse
    {
        $video = Video::with(['creator', 'category', 'disciplines', 'tags'])
            ->published()
            ->findOrFail($videoId);

        return response()->json($video);
    }


    // ── fetchCreatorVideos ────────────────────────────────────────────────────
    #[OA\Get(
        path: '/api/creators/{creatorId}/videos',
        summary: 'Vidéos d\'un créateur',
        description: 'Retourne les vidéos d\'un créateur spécifique avec des filtres optionnels.',
        tags: ['Videos'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des vidéos du créateur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Liste des vidéos du créateur récupérée')
            ]
        )
    )]
    public function creatorVideos(Request $request, string $creatorId): JsonResponse
    {
        $request->validate([
            'category' => 'sometimes|string',
            'limit'    => 'sometimes|integer|min:1|max:50',
            'offset'   => 'sometimes|integer|min:0',
        ]);

        $query = Video::with(['category', 'disciplines', 'tags'])
            ->published()
            ->where('creator_id', $creatorId);

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        $videos = $query
            ->latest('published_at')
            ->skip($request->integer('offset', 0))
            ->take($request->integer('limit', 20))
            ->get();

        return response()->json($videos);
    }
}
