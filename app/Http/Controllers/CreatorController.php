<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorController extends Controller
{
    // ── fetchCreatorsList ─────────────────────────────────────────────────────
    // Grille /creators — uniquement les créateurs avec videoCount > 0

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'discipline' => 'sometimes|string',
            'search'     => 'sometimes|string|max:100',
            'sort'       => 'sometimes|in:recent,level,videos,new',
            'limit'      => 'sometimes|integer|min:1|max:100',
            'offset'     => 'sometimes|integer|min:0',
        ]);

        $query = User::with([
                'disciplines' => fn ($q) => $q->limit(3), // 3 premières → CreatorCard
                'socialLinks',
            ])
            ->withCount('publishedVideos as video_count')
            ->withCount('followers')
            ->where('role', 'creator')
            ->where('is_banned', false)
            ->having('video_count', '>', 0);

        // Filtre par discipline
        if ($request->filled('discipline')) {
            $query->whereHas('disciplines', fn ($q) =>
                $q->where('slug', $request->input('discipline'))
            );
        }

        // Recherche par pseudo
        if ($request->filled('search')) {
            $query->where('pseudo', 'like', '%' . $request->input('search') . '%');
        }

        // Tri
        match ($request->input('sort', 'recent')) {
            'level'   => $query->orderByDesc('level'),
            'videos'  => $query->orderByDesc('video_count'),
            'new'     => $query->latest('created_at'),
            default   => $query->orderByDesc('last_login_at'), // recent
        };

        // Spotlight premium en tête (tirage aléatoire parmi les éligibles)
        // Séparation premium / standard côté client via le champ `plan`
        $creators = $query
            ->skip($request->integer('offset', 0))
            ->take($request->integer('limit', 30))
            ->get();

        return response()->json(CreatorResource::collection($creators));
    }

    // ── fetchCreatorProfile ───────────────────────────────────────────────────
    // Profil complet par pseudo — route publique /:pseudo

    public function show(string $pseudo): JsonResponse
    {
        $creator = User::with([
                'disciplines',
                'socialLinks',
                'featuredVideos',
                'agendaItems' => fn ($q) => $q->upcoming()->limit(5),
                'activeSubscription.plan',
            ])
            ->withCount('publishedVideos as video_count')
            ->withCount('followers')
            ->where('pseudo', $pseudo)
            ->where('is_banned', false)
            ->firstOrFail();

        return response()->json($creator);
    }

    // ── fetchRecommendedCreators ──────────────────────────────────────────────
    // Scoring côté serveur : préférence pour les créateurs non suivis

    public function recommended(Request $request): JsonResponse
    {
        $request->validate([
            'limit'       => 'sometimes|integer|min:1|max:20',
            'exclude_ids' => 'sometimes|array',
            'exclude_ids.*' => 'uuid',
        ]);

        $userId     = $request->user()?->id;
        $limit      = $request->integer('limit', 6);
        $excludeIds = $request->input('exclude_ids', []);

        $query = User::with(['disciplines' => fn ($q) => $q->limit(3)])
            ->withCount('publishedVideos as video_count')
            ->withCount('followers')
            ->where('role', 'creator')
            ->where('is_banned', false)
            ->having('video_count', '>', 0)
            ->whereNotIn('id', $excludeIds);

        // Exclure ceux déjà suivis par l'utilisateur connecté
        if ($userId) {
            $query->whereNotIn('id', function ($sub) use ($userId) {
                $sub->select('followed_id')
                    ->from('follows')
                    ->where('follower_id', $userId);
            })->where('id', '!=', $userId);
        }

        // Scoring : premium d'abord, puis niveau, puis random
        $creators = $query
            ->orderByRaw("CASE WHEN plan = 'premium' THEN 0 WHEN plan = 'pro' THEN 1 ELSE 2 END")
            ->orderByDesc('level')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return response()->json(CreatorResource::collection($creators));
    }
}
