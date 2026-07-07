<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // null = top-level comment; set = reply to another comment
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedBigInteger('likes_count')->default(0); // denormalized
            $table->timestamps();
            $table->softDeletes();

            // Indexes for fetching comments per post, replies per comment
            $table->index(['post_id', 'parent_id', 'created_at']);
            $table->index(['parent_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
