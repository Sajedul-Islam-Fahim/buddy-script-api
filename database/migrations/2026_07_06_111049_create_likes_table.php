<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Polymorphic likes table covering posts, comments, and replies.
         * likeable_type: 'App\Models\Post' | 'App\Models\Comment'
         * likeable_id:   the target's primary key
         *
         * Unique constraint prevents double-liking.
         * Composite index optimises both "who liked this?" and "did I like this?" queries.
         */
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('likeable'); // creates likeable_type + likeable_id + index
            $table->timestamps();

            // Prevent duplicate likes
            $table->unique(['user_id', 'likeable_type', 'likeable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
