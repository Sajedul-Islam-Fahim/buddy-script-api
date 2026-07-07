<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    // ── Feed ──────────────────────────────────────────────────────
    public function test_feed_requires_authentication(): void
    {
        $this->getJson('/api/posts')->assertUnauthorized();
    }

    public function test_feed_returns_public_posts(): void
    {
        $other = User::factory()->create();
        Post::factory()->create(['user_id' => $other->id, 'visibility' => 'public']);
        Post::factory()->create(['user_id' => $other->id, 'visibility' => 'private']);

        $data = $this->withToken($this->token)
                     ->getJson('/api/posts')
                     ->assertOk()
                     ->json('data');

        // Only public post visible (private belongs to $other)
        $this->assertCount(1, $data);
    }

    public function test_feed_includes_own_private_posts(): void
    {
        Post::factory()->create(['user_id' => $this->user->id, 'visibility' => 'private']);
        Post::factory()->create(['user_id' => $this->user->id, 'visibility' => 'public']);

        $data = $this->withToken($this->token)
                     ->getJson('/api/posts')
                     ->assertOk()
                     ->json('data');

        $this->assertCount(2, $data);
    }

    // ── Create post ───────────────────────────────────────────────
    public function test_user_can_create_public_post(): void
    {
        $this->withToken($this->token)
             ->postJson('/api/posts', [
                 'body'       => 'Hello world!',
                 'visibility' => 'public',
             ])->assertStatus(201)
               ->assertJsonPath('post.visibility', 'public')
               ->assertJsonPath('post.body', 'Hello world!');
    }

    public function test_user_can_create_private_post(): void
    {
        $this->withToken($this->token)
             ->postJson('/api/posts', [
                 'body'       => 'This is private.',
                 'visibility' => 'private',
             ])->assertStatus(201)
               ->assertJsonPath('post.visibility', 'private');
    }

    public function test_post_body_is_required(): void
    {
        $this->withToken($this->token)
             ->postJson('/api/posts', ['visibility' => 'public'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['body']);
    }

    // ── Delete post ───────────────────────────────────────────────
    public function test_user_can_delete_own_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
             ->deleteJson("/api/posts/{$post->id}")
             ->assertOk();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_user_cannot_delete_others_post(): void
    {
        $other = User::factory()->create();
        $post  = Post::factory()->create(['user_id' => $other->id]);

        $this->withToken($this->token)
             ->deleteJson("/api/posts/{$post->id}")
             ->assertForbidden();
    }

    // ── Like ──────────────────────────────────────────────────────
    public function test_user_can_like_and_unlike_a_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id, 'visibility' => 'public']);

        // Like
        $this->withToken($this->token)
             ->postJson("/api/posts/{$post->id}/like")
             ->assertOk()
             ->assertJsonPath('is_liked', true)
             ->assertJsonPath('likes_count', 1);

        // Unlike (toggle)
        $this->withToken($this->token)
             ->postJson("/api/posts/{$post->id}/like")
             ->assertOk()
             ->assertJsonPath('is_liked', false)
             ->assertJsonPath('likes_count', 0);
    }
}
