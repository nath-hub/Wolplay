<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ── fetchDashboardFeed ────────────────────────────────────────────────────
    // GET /v1/dashboard/feed
    // Scoring côté serveur — posts des créateurs suivis + propres posts
    // @returns { items: DashboardPost[], nextOffset: number|null, hasMore: boolean }

    public function feed(Request $request): JsonResponse
    {
        $request->validate([
            'offset' => 'sometimes|integer|min:0',
            'limit'  => 'sometimes|integer|min:1|max:30',
        ]);

        $userId = $request->user()->id;
        $limit  = $request->integer('limit', 10);
        $offset = $request->integer('offset', 0);

        // Auteurs à inclure : l'utilisateur lui-même + ceux qu'il suit
        $authorIds = DB::table('follows')
            ->where('follower_id', $userId)
            ->pluck('followed_id')
            ->push($userId);

        $query = Post::with(['author'])
            ->published()
            ->whereIn('author_id', $authorIds)
            ->latest('created_at');

        $total = $query->count();
        $items = $query->skip($offset)->take($limit)->get();

        $nextOffset = ($offset + $limit < $total) ? $offset + $limit : null;

        return response()->json([
            'items'      => $items,
            'nextOffset' => $nextOffset,
            'hasMore'    => $nextOffset !== null,
        ]);
    }

    // ── createDashboardPost ───────────────────────────────────────────────────
    // POST /v1/dashboard/posts
    // @returns DashboardPost

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'post_type'     => 'required|in:video,wip,photo,text',
            'content'       => 'nullable|string|max:2000',
            'media_url'     => 'nullable|url',
            'thumbnail_url' => 'nullable|url',
            'video_id'      => 'nullable|uuid|exists:videos,id',
            'is_wip'        => 'sometimes|boolean',
            'wip_progress'  => 'sometimes|integer|min:0|max:100',
        ]);

        $userId = $request->user()->id;

        // Un seul WIP épinglé possible par créateur
        if ($request->boolean('is_wip') && $request->boolean('is_pinned', false)) {
            Post::where('author_id', $userId)
                ->where('is_pinned', true)
                ->where('is_wip', true)
                ->update(['is_pinned' => false]);
        }

        $post = Post::create([
            'author_id'     => $userId,
            'post_type'     => $request->input('post_type'),
            'content'       => $request->input('content'),
            'media_url'     => $request->input('media_url'),
            'thumbnail_url' => $request->input('thumbnail_url'),
            'is_wip'        => $request->boolean('is_wip', false),
            'wip_progress'  => $request->integer('wip_progress', 0),
            'is_pinned'     => false,
            'status'        => 'published',
        ]);

        return response()->json($post->load('author'), 201);
    }

    // ── deleteDashboardPost ───────────────────────────────────────────────────
    // DELETE /v1/dashboard/posts/{postId}
    // @returns void (204)

    public function destroy(Request $request, string $postId): JsonResponse
    {
        $post = Post::where('author_id', $request->user()->id)
            ->findOrFail($postId);

        $post->delete();

        return response()->json(null, 204);
    }

    // ── updateWipPost ─────────────────────────────────────────────────────────
    // PATCH /v1/dashboard/wip
    // Met à jour le WIP épinglé courant (progression + contenu)
    // @returns DashboardPost

    public function updateWip(Request $request): JsonResponse
    {
        $request->validate([
            'post_id'      => 'required|uuid',
            'content'      => 'nullable|string|max:2000',
            'wip_progress' => 'sometimes|integer|min:0|max:100',
            'media_url'    => 'nullable|url',
        ]);

        $post = Post::where('author_id', $request->user()->id)
            ->where('is_wip', true)
            ->findOrFail($request->input('post_id'));

        $post->update($request->only(['content', 'wip_progress', 'media_url']));

        return response()->json($post->load('author'));
    }

    // ── toggleWipPin ──────────────────────────────────────────────────────────
    // POST /v1/dashboard/wip/{postId}/pin
    // @returns { is_pinned: boolean }

    public function toggleWipPin(Request $request, string $postId): JsonResponse
    {
        $userId = $request->user()->id;

        $post = Post::where('author_id', $userId)
            ->where('is_wip', true)
            ->findOrFail($postId);

        DB::transaction(function () use ($userId, $post) {
            if ($post->is_pinned) {
                // Désépingler
                $post->update(['is_pinned' => false]);
            } else {
                // Désépingler l'éventuel WIP déjà épinglé
                Post::where('author_id', $userId)
                    ->where('is_pinned', true)
                    ->where('is_wip', true)
                    ->update(['is_pinned' => false]);

                // Épingler celui-ci
                $post->update(['is_pinned' => true]);
            }
        });

        $post->refresh();

        return response()->json(['is_pinned' => $post->is_pinned]);
    }
}
