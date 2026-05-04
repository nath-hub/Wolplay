<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    /**
     * Feed de posts — scoring côté serveur obligatoire.
     * Voir BACKEND_HANDOFF.md pour la spec de scoring complète.
     *
     * @return \Illuminate\Http\JsonResponse
     *   { items: DashboardPost[], nextOffset: int|null, hasMore: bool }
     */
    public function feed(Request $request)
    {
        $userId = $request->query('userId', $request->user()?->id);
        $offset = (int) $request->query('offset', 0);
        $limit  = (int) $request->query('limit', 10);

        $followingIds = $request->user()
            ?->following()
            ->pluck('creators.id')
            ?? collect();

        $total = \App\Models\Post::query()
            ->whereIn('creator_id', $followingIds)
            ->count();

        $items = \App\Models\Post::query()
            ->whereIn('creator_id', $followingIds)
            ->with(['creator', 'media'])
            // TODO: remplacer orderByDesc('score') une fois le scoring implémenté (BACKEND_HANDOFF.md)
            ->orderByDesc('published_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $nextOffset = ($offset + $limit < $total) ? $offset + $limit : null;

        return response()->json([
            'items'      => $items,
            'nextOffset' => $nextOffset,
            'hasMore'    => $nextOffset !== null,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse DashboardPost
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => ['nullable', 'string', 'max:255'],
            'body'       => ['required', 'string'],
            'media'      => ['nullable', 'array'],
            'media.*.url'  => ['required_with:media', 'url'],
            'media.*.type' => ['required_with:media', 'in:image,video,link'],
        ]);

        $post = \App\Models\Post::create([
            'creator_id'   => $request->user()->id,
            'title'        => $data['title'] ?? null,
            'body'         => $data['body'],
            'published_at' => now(),
        ]);

        if (!empty($data['media'])) {
            $post->media()->createMany($data['media']);
        }

        $post->load(['creator', 'media']);

        return response()->json($post, 201);
    }
}
