<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic test data.
     *
     * Run with: php artisan db:seed
     * Fresh seed: php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        // ── 1. Create a known test user (easy login during development) ──
        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name'  => 'User',
                'password'   => Hash::make('password'),
            ]
        );

        // ── 2. Create 9 additional random users ──────────────────────────
        $users = collect([$testUser]);

        $names = [
            ['Alice', 'Johnson'],
            ['Bob', 'Smith'],
            ['Carol', 'Williams'],
            ['David', 'Brown'],
            ['Eva', 'Davis'],
            ['Frank', 'Miller'],
            ['Grace', 'Wilson'],
            ['Henry', 'Moore'],
            ['Iris', 'Taylor'],
        ];

        foreach ($names as [$first, $last]) {
            $users->push(User::firstOrCreate(
                ['email' => strtolower($first) . '@example.com'],
                [
                    'first_name' => $first,
                    'last_name'  => $last,
                    'password'   => Hash::make('password'),
                ]
            ));
        }

        // ── 3. Create posts for each user ────────────────────────────────
        $sampleBodies = [
            'Just shipped a new feature! Really excited about what we built this week. 🚀',
            'Morning coffee and code — the perfect combination. ☕',
            'Anyone else find that rubber duck debugging actually works?',
            'Hot take: dark mode is not just aesthetic, it actually helps me focus.',
            'Spent 3 hours debugging only to find a missing semicolon. Classic.',
            'Reading "Clean Code" again. Every re-read reveals something new.',
            'The best part of working remotely? My commute is exactly 12 steps.',
            'Just hit 100 commits on my side project. Small wins matter! 🎉',
            'Why do we call it "going live" when deploying feels like jumping off a cliff?',
            'Pair programming session today was incredibly productive. Highly recommend.',
            'Stack Overflow is down. I have forgotten how to code.',
            'Types are just documentation that actually gets checked. Fight me.',
        ];

        $allPosts = collect();

        foreach ($users as $user) {
            // Each user gets 2–4 posts
            $count = rand(2, 4);
            for ($i = 0; $i < $count; $i++) {
                $post = Post::create([
                    'user_id'    => $user->id,
                    'body'       => $sampleBodies[array_rand($sampleBodies)],
                    'visibility' => $i === 0 ? 'private' : 'public', // first post is private
                    'image'      => null,
                ]);
                $allPosts->push($post);
            }
        }

        // ── 4. Add likes to posts ────────────────────────────────────────
        $publicPosts = $allPosts->where('visibility', 'public');

        foreach ($publicPosts as $post) {
            // 3–7 random users like each public post
            $likers = $users->random(rand(3, 7));
            foreach ($likers as $liker) {
                $already = Like::where([
                    'user_id'       => $liker->id,
                    'likeable_type' => Post::class,
                    'likeable_id'   => $post->id,
                ])->exists();

                if (! $already) {
                    Like::create([
                        'user_id'       => $liker->id,
                        'likeable_type' => Post::class,
                        'likeable_id'   => $post->id,
                    ]);
                    $post->increment('likes_count');
                }
            }
        }

        // ── 5. Add comments and replies to posts ─────────────────────────
        $commentBodies = [
            'Great point! Totally agree.',
            'This is so relatable 😂',
            'Have you tried pair programming for this?',
            'Same here! It happens to the best of us.',
            'Could not agree more with this.',
            'This made my day, thanks for sharing!',
            'Interesting perspective. I had not thought of it that way.',
            'Haha yes! Every single time.',
        ];

        $replyBodies = [
            'Exactly what I was thinking!',
            'Good point, I will try that.',
            'Thanks for the tip!',
            'Ha, true!',
            'Agreed 100%.',
        ];

        foreach ($publicPosts->take(15) as $post) {
            // 1–3 comments per post
            $commentCount = rand(1, 3);
            for ($c = 0; $c < $commentCount; $c++) {
                $commenter = $users->random();
                $comment   = Comment::create([
                    'post_id'   => $post->id,
                    'user_id'   => $commenter->id,
                    'parent_id' => null,
                    'body'      => $commentBodies[array_rand($commentBodies)],
                ]);
                $post->increment('comments_count');

                // Like the comment
                $commentLikers = $users->random(rand(0, 3));
                foreach ($commentLikers as $liker) {
                    $already = Like::where([
                        'user_id'       => $liker->id,
                        'likeable_type' => Comment::class,
                        'likeable_id'   => $comment->id,
                    ])->exists();
                    if (! $already) {
                        Like::create([
                            'user_id'       => $liker->id,
                            'likeable_type' => Comment::class,
                            'likeable_id'   => $comment->id,
                        ]);
                        $comment->increment('likes_count');
                    }
                }

                // 0–2 replies per comment
                $replyCount = rand(0, 2);
                for ($r = 0; $r < $replyCount; $r++) {
                    $replier = $users->random();
                    Comment::create([
                        'post_id'   => $post->id,
                        'user_id'   => $replier->id,
                        'parent_id' => $comment->id,
                        'body'      => $replyBodies[array_rand($replyBodies)],
                    ]);
                    $post->increment('comments_count');
                }
            }
        }

        $this->command->info('✅  Seeded 10 users, ' . $allPosts->count() . ' posts, comments, replies, and likes.');
        $this->command->info('📧  Test login → email: test@example.com  |  password: password');
    }
}
