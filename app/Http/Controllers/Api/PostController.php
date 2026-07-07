<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * GET /api/posts
     * Public feed + authenticated user's own private posts.
     * Cursor-paginated for performance at scale.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        /**
         * Strategy for millions of posts:
         * - Cursor pagination avoids expensive OFFSET scans.
         * - We fetch public posts OR the auth user's own posts in one query.
         * - Denormalised counts avoid COUNT(*) subqueries on every row.
         */
        $posts = Post::with(['user:id,first_name,last_name,avatar'])
            ->where(function ($q) use ($userId) {
                $q->where('visibility', 'public')
                  ->orWhere('user_id', $userId); // include own private posts
            })
            ->orderByDesc('created_at')
            ->cursorPaginate(15);

        // Attach viewer's like state in a single batch query
        $postIds    = $posts->pluck('id');
        $likedIds   = $request->user()
            ->likes()
            ->where('likeable_type', Post::class)
            ->whereIn('likeable_id', $postIds)
            ->pluck('likeable_id')
            ->flip();

        $items = $posts->map(fn ($post) => $this->formatPost($post, $likedIds->has($post->id)));

        return response()->json([
            'data'          => $items,
            'next_cursor'   => $posts->nextCursor()?->encode(),
            'has_more'      => $posts->hasMorePages(),
        ]);
    }

    /**
     * POST /api/posts
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'body'       => 'required|string|max:5000',
            'image'      => 'nullable|image|max:5120', // 5 MB
            'visibility' => 'required|in:public,private',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('post-images', 'public');
        }

        $post = Post::create([
            'user_id'    => $request->user()->id,
            'body'       => $request->body,
            'image'      => $imagePath,
            'visibility' => $request->visibility,
        ]);

        $post->load('user:id,first_name,last_name,avatar');

        return response()->json(['post' => $this->formatPost($post, false)], 201);
    }

    /**
     * DELETE /api/posts/{post}
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        if ($post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    // ── Private helpers ──────────────────────────────────────────
    private function formatPost(Post $post, bool $isLiked): array
    {
        return [
            'id'             => $post->id,
            'body'           => $post->body,
            'image'          => $post->image ? Storage::url($post->image) : null,
            'visibility'     => $post->visibility,
            'likes_count'    => $post->likes_count,
            'comments_count' => $post->comments_count,
            'is_liked'       => $isLiked,
            'user'           => $post->user,
            'created_at'     => $post->created_at,
        ];
    }
}
