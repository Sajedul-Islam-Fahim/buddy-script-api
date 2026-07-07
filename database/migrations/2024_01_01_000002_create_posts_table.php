<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('image')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->unsignedBigInteger('likes_count')->default(0);  // denormalized counter
            $table->unsignedBigInteger('comments_count')->default(0); // denormalized counter
            $table->timestamps();
            $table->softDeletes();

            // Indexes optimized for feed queries (millions of rows)
            $table->index(['visibility', 'created_at']); // public feed
            $table->index(['user_id', 'created_at']);    // user's own posts
            $table->index('created_at');                  // sorting
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
