<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowsController extends Controller
{
    // ── followCreator ─────────────────────────────────────────────────────────
    // POST /v1/creators/{creatorId}/follow
    // @returns { following: true }
    public function follow(Request $request, string $creatorId)
    {
        $userId = $request->user()->id;

        User::where('is_banned', false)->findOrFail($creatorId);

        $request->user()->following()->syncWithoutDetaching([$creatorId]);

        return response()->json(['following' => true]);
    }

    // ── unfollowCreator ───────────────────────────────────────────────────────
    // DELETE /v1/creators/{creatorId}/follow
    // @returns { following: false }
    public function unfollow(Request $request, string $creatorId)
    {
        $request->user()->following()->detach($creatorId);

        return response()->json(['following' => false]);
    }

    /**
     * Recommandations personnalisées — scoring côté serveur obligatoire.
     * Voir BACKEND_HANDOFF.md pour la spec de scoring complète.
     *
     * @return \Illuminate\Http\JsonResponse CreatorCard[]
     */
    public function recommended(Request $request)
    {
        $limit      = (int) $request->query('limit', 6);
        $excludeIds = $request->query('excludeIds', []);

        $followingIds = $request->user()->following()->pluck('creators.id');

        $creators = User::query()
            ->whereNotIn('id', array_merge($followingIds->toArray(), (array) $excludeIds))
            ->where('id', '!=', $request->user()->id)
            ->withCount('followers')
            ->withCount('videos')
            ->where('video_count', '>', 0)
            // TODO: remplacer par le scoring serveur complet (BACKEND_HANDOFF.md)
            ->orderByDesc('followers_count')
            ->limit($limit)
            ->get();

        return response()->json($creators);
    }



    // ── fetchFollowStatus ─────────────────────────────────────────────────────
    // GET /v1/creators/{creatorId}/follow
    // @returns { following: boolean }

    public function status(Request $request, string $creatorId): JsonResponse
    {
        $userId = $request->user()->id;

        $following = DB::table('follows')
            ->where('follower_id', $userId)
            ->where('followed_id', $creatorId)
            ->exists();

        return response()->json(['following' => $following]);
    }

    // ── fetchFollowing ────────────────────────────────────────────────────────
    // GET /v1/users/{userId}/following
    // @returns CreatorCard[]

    public function following(string $userId): JsonResponse
    {
        $creators = User::with(['disciplines' => fn($q) => $q->limit(3)])
            ->withCount('publishedVideos as video_count')
            ->withCount('followers')
            ->whereIn('id', function ($sub) use ($userId) {
                $sub->select('followed_id')
                    ->from('follows')
                    ->where('follower_id', $userId);
            })
            ->get();

        return response()->json(
            $creators->map(fn($c) => [
                'id' => $c->id,
                'pseudo' => $c->pseudo ?? '',
                'avatar' => $c->avatar ?? null,
                'followersCount' => $c->followers_count,
                'videoCount' => $c->video_count,
                'disciplines' => $c->disciplines ?? [],
            ])
        );

    }


    // ── fetchFollowers ────────────────────────────────────────────────────────
    // GET /v1/users/{userId}/followers
    // @returns CreatorCard[]

    public function followers(string $userId): JsonResponse
    {
        $followers = User::with(['disciplines' => fn($q) => $q->limit(3)])
            ->withCount('publishedVideos as video_count')
            ->withCount('followers')
            ->whereIn('id', function ($sub) use ($userId) {
                $sub->select('follower_id')
                    ->from('follows')
                    ->where('followed_id', $userId);
            })
            ->get();

        return response()->json(
            $followers->map(fn($c) => [
                'id' => $c->id,
                'pseudo' => $c->pseudo ?? '',
                'avatar' => $c->avatar ?? null,
                'followersCount' => $c->followers_count,
                'videoCount' => $c->video_count,
                'disciplines' => $c->disciplines ?? [],
            ])
        );

    }
}
