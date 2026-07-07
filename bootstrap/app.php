<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // ── TEMPORARY one-time deploy helper ─────────────────────────
            // Visit /deploy/{DEPLOY_TOKEN} once to run key:generate (only if
            // unset), migrate, storage:link, and db:seed on hosts with no
            // terminal/SSH access. Registered outside the "web" group (no
            // session/CSRF — there may be no "sessions" table yet on a
            // fresh database).
            // DELETE THIS BLOCK right after you've used it — it executes
            // artisan commands over plain HTTP and must not stay live.
            Route::get('/deploy/{token}', function (string $token) {
                $expected = env('DEPLOY_TOKEN', '');

                if ($expected === '' || ! hash_equals($expected, $token)) {
                    abort(404);
                }

                $output = '';

                if (empty(config('app.key'))) {
                    Artisan::call('key:generate', ['--force' => true]);
                    $output .= "$ php artisan key:generate\n" . Artisan::output() . "\n";
                } else {
                    $output .= "$ php artisan key:generate  (skipped — APP_KEY already set)\n\n";
                }

                Artisan::call('migrate', ['--force' => true]);
                $output .= "$ php artisan migrate --force\n" . Artisan::output() . "\n";

                Artisan::call('storage:link');
                $output .= "$ php artisan storage:link\n" . Artisan::output() . "\n";

                Artisan::call('db:seed', ['--force' => true]);
                $output .= "$ php artisan db:seed --force\n" . Artisan::output() . "\n";

                return response('<pre>' . e($output) . '</pre>');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Stateless API — disable CSRF for API routes
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for validation errors on API routes
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });
    })
    ->create();
