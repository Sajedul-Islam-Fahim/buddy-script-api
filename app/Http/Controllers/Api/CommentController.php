<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * GET /api/posts/{post}/comments
     * Returns top-level comments with their replies nested one level.
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        // Authorise: private posts only visible to owner
        if ($post->visibility === 'private' && $post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $comments = Comment::with([
                'user:id,first_name,last_name,avatar',
                'replies.user:id,first_name,last_name,avatar',
            ])
            ->where('post_id', $post->id)
            ->whereNull('parent_id')   // top-level only
            ->orderBy('created_at')
            ->get();

        // Batch-fetch viewer like state for comments + replies
        $allIds     = $comments->pluck('id')
            ->merge($comments->flatMap(fn ($c) => $c->replies->pluck('id')));
        $likedIds   = $request->user()
            ->likes()
            ->where('likeable_type', Comment::class)
            ->whereIn('likeable_id', $allIds)
            ->pluck('likeable_id')
            ->flip();

        $formatted = $comments->map(fn ($c) => $this->formatComment($c, $likedIds));

        return response()->json(['data' => $formatted]);
    }

    /**
     * POST /api/posts/{post}/comments
     * Creates a top-level comment or a reply (if parent_id supplied).
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        if ($post->visibility === 'private' && $post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'body'      => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        // Ensure parent belongs to this post (prevents cross-post injection)
        if ($request->parent_id) {
            $parent = Comment::findOrFail($request->parent_id);
            if ($parent->post_id !== $post->id) {
                return response()->json(['message' => 'Invalid parent comment.'], 422);
            }
        }

        $comment = Comment::create([
            'post_id'   => $post->id,
            'user_id'   => $request->user()->id,
            'parent_id' => $request->parent_id,
            'body'      => $request->body,
        ]);

        // Increment denormalised counter
        $post->increment('comments_count');

        $comment->load('user:id,first_name,last_name,avatar');

        return response()->json([
            'comment' => $this->formatComment($comment, collect()),
        ], 201);
    }

    /**
     * DELETE /api/comments/{comment}
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $comment->post->decrement('comments_count');
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    // ── Private helpers ──────────────────────────────────────────
    private function formatComment(Comment $comment, $likedIds): array
    {
        return [
            'id'          => $comment->id,
            'body'        => $comment->body,
            'likes_count' => $comment->likes_count,
            'is_liked'    => $likedIds->has($comment->id),
            'user'        => $comment->user,
            'created_at'  => $comment->created_at,
            'replies'     => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn ($r) => $this->formatComment($r, $likedIds))
                : [],
        ];
    }
}
