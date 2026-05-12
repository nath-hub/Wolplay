<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AtelierController extends Controller
{
    /**
     * GET /api/atelier/feed
     * Récupère le feed social de l'Atelier (paginated)
     */
    public function feed(Request $request)
    {
        // Utilise (int) pour convertir explicitement
        $offset = (int) $request->query('offset', 0);
        $limit = (int) $request->query('limit', 10);

        $query = Post::with('author:id,pseudo,avatar_url')
            ->where('status', 'published')
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $items = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn($post) => $this->formatPostResponse($post));

        // Maintenant l'addition fonctionne parfaitement
        $nextOffset = $offset + $limit;
        $hasMore = $nextOffset < $total;

        return response()->json([
            'items'      => $items,
            'nextOffset' => $hasMore ? $nextOffset : null,
            'hasMore'    => $hasMore,
        ]);
    }

    /**
     * GET /api/atelier/posts?creatorId=:id
     * Récupère les posts d'un créateur spécifique
     */
    public function postsByCreator(Request $request, $creatorId = null)
    {
        $creatorId = $creatorId ?? $request->query('creatorId');
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 0);

        $query = Post::with('author:id,pseudo,avatar')
            ->where('author_id', $creatorId)
            ->where('status', 'published')
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $items = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn($post) => $this->formatPostResponse($post));

        return response()->json([
            'items'      => $items,
            'nextOffset' => ($offset + $limit < $total) ? $offset + $limit : null,
            'hasMore'    => $offset + $limit < $total,
        ]);
    }

    /**
     * POST /api/atelier/posts
     * Crée un nouveau post dans l'Atelier
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'text'              => 'required|string|max:5000',
            'images'            => 'nullable|array',
            'images.*'          => 'url',
        ]);

        $post = Post::create([
            'author_id'  => $user->id,
            'post_type'  => 'text', // Type Atelier par défaut
            'content'    => $validated['text'],
            'images'     => $validated['images'] ?? [],
            'status'     => 'published',
            'type'       => null,
            'source_snapshot' => null,
        ]);

        return response()->json(
            $post,
            201
        );
    }

    /**
     * PATCH /api/atelier/posts/:postId
     * Modifie un post existant
     */
    public function update(Request $request, $postId)
    {
        $user = Auth::user();
        $post = Post::findOrFail($postId);

        // Vérifier que l'user est l'auteur du post
        if ($post->author_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'text'   => 'required|string|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'url',
        ]);

        $post->update([
            'content' => $validated['text'],
            'images'  => $validated['images'] ?? [],
        ]);

        return response()->json($this->formatPostResponse($post));
    }

    /**
     * DELETE /api/atelier/posts/:postId
     * Supprime un post et tous les posts qui le partagent
     */
    public function destroy($postId)
    {
        $user = Auth::user();
        $post = Post::findOrFail($postId);

        // Vérifier que l'user est l'auteur du post
        if ($post->author_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Cascade: supprimer tous les posts qui partagent celui-ci
        Post::where('type', 'shared_post')
            ->whereJsonContains('source_snapshot->sourceId', $postId)
            ->delete();

        $post->delete();

        return response()->noContent();
    }

    /**
     * Formatte la réponse d'un post pour l'API
     */
    private function formatPostResponse(Post $post): array
    {
        $response = [
            'id'              => $post->id,
            'authorId'        => $post->author_id,
            'text'            => $post->content ?? '',
            'images'          => $post->images ?? [],
            'imageCount'      => $post->image_count,
            'createdAt'       => $post->created_at->toIso8601String(),
            'updatedAt'       => $post->updated_at?->toIso8601String(),
            'type'            => $post->type ?? "shared_etabli",
            'sourceSnapshot'  => $post->source_snapshot ? $post->source_snapshot : (object)[],
            'author'          => [
                'id'     => $post->author->id,
                'pseudo' => $post->author->pseudo,
                'avatar' => $post->author->avatar,
            ],
        ];

        return $response;
    }
}
