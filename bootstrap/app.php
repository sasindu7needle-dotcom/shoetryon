<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS at its proxy; trust it so URLs are generated as https.
        $middleware->trustProxies(at: '*');

        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Photos bigger than PHP's post_max_size never reach validation; send the admin back with a reason.
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if ($request->is('admin/*') && ! $request->expectsJson()) {
                return back()->with('error', 'The photos are too big for the server to accept ('.ini_get('post_max_size').' in total). Use smaller photos or raise post_max_size and upload_max_filesize in php.ini.');
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
