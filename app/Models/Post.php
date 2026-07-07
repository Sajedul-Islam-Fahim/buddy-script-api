<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'body',
        'image',
        'visibility',
    ];

    protected $casts = [
        'likes_count'    => 'integer',
        'comments_count' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        // Only top-level comments (not replies)
        return $this->hasMany(Comment::class)->whereNull('parent_id');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    // ── Scopes ───────────────────────────────────────────────────

    /**
     * Scope for the public feed (most-recent first).
     * Uses the composite index (visibility, created_at).
     */
    public function scopePublicFeed($query)
    {
        return $query->where('visibility', 'public')
                     ->orderByDesc('created_at');
    }

    /**
     * Scope for a user's own posts (public + private).
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
                     ->orderByDesc('created_at');
    }
}
