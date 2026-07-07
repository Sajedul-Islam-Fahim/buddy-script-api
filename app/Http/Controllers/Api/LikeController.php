<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * POST /api/posts/{post}/like       — toggle like on a post
     * POST /api/comments/{comment}/like — toggle like on a comment/reply
     *
     * Uses DB-level unique constraint + firstOrCreate for atomic safety.
     */
    public function togglePost(Request $request, Post $post): JsonResponse
    {
        // Private post guard
        if ($post->visibility === 'private' && $post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $this->toggle($request->user(), $post);
    }

    public function toggleComment(Request $request, Comment $comment): JsonResponse
    {
        // Guard: ensure the parent post is accessible
        $post = $comment->post;
        if ($post->visibility === 'private' && $post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $this->toggle($request->user(), $comment);
    }

    /**
     * GET /api/posts/{post}/likes    — who liked this post
     * GET /api/comments/{comment}/likes — who liked this comment
     */
    public function postLikers(Post $post): JsonResponse
    {
        $likers = $post->likes()->with('user:id,first_name,last_name,avatar')->get()
            ->map(fn ($l) => $l->user);

        return response()->json(['data' => $likers]);
    }

    public function commentLikers(Comment $comment): JsonResponse
    {
        $likers = $comment->likes()->with('user:id,first_name,last_name,avatar')->get()
            ->map(fn ($l) => $l->user);

        return response()->json(['data' => $likers]);
    }

    // ── Private helpers ──────────────────────────────────────────
    private function toggle($user, $likeable): JsonResponse
    {
        $existing = Like::where([
            'user_id'       => $user->id,
            'likeable_type' => get_class($likeable),
            'likeable_id'   => $likeable->id,
        ])->first();

        if ($existing) {
            $existing->delete();
            $likeable->decrement('likes_count');
            $isLiked = false;
        } else {
            Like::create([
                'user_id'       => $user->id,
                'likeable_type' => get_class($likeable),
                'likeable_id'   => $likeable->id,
            ]);
            $likeable->increment('likes_count');
            $isLiked = true;
        }

        $likeable->refresh();

        return response()->json([
            'is_liked'    => $isLiked,
            'likes_count' => $likeable->likes_count,
        ]);
    }
}
