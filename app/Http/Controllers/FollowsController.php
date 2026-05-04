<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FollowsController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse { following: true }
     */
    public function follow(Request $request, string $creatorId)
    {
        $userId = $request->user()->id;

        $request->user()->following()->syncWithoutDetaching([$creatorId]);

        return response()->json(['following' => true]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse { following: false }
     */
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
}
