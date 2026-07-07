<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — buddy-script
|--------------------------------------------------------------------------
| All routes are prefixed with /api automatically by Laravel.
| Rate limiting is applied via the "api" throttle middleware (60 req/min).
*/

// ── Public auth routes ──────────────────────────────────────────────────
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Protected routes ────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Feed & posts
    Route::get('/posts',          [PostController::class, 'index']);
    Route::post('/posts',         [PostController::class, 'store']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    // Like / unlike a post
    Route::post('/posts/{post}/like',       [LikeController::class, 'togglePost']);
    Route::get('/posts/{post}/likes',       [LikeController::class, 'postLikers']);

    // Comments on a post
    Route::get('/posts/{post}/comments',    [CommentController::class, 'index']);
    Route::post('/posts/{post}/comments',   [CommentController::class, 'store']);
    Route::delete('/comments/{comment}',    [CommentController::class, 'destroy']);

    // Like / unlike a comment or reply
    Route::post('/comments/{comment}/like', [LikeController::class, 'toggleComment']);
    Route::get('/comments/{comment}/likes', [LikeController::class, 'commentLikers']);
});
