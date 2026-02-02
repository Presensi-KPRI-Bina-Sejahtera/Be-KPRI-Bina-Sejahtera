<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Http\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only: jangan redirect guest ke route('login') (route ga ada), cukup biarkan jadi 401 JSON.
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'status' => 'unauthenticated',
                'message' => 'Anda belum login atau sesi Anda telah berakhir.',
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Data tidak ditemukan.',
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $statusCode = $e->getStatusCode();

            return response()->json([
                'status' => $statusCode === 401 ? 'unauthenticated' : ($statusCode === 403 ? 'forbidden' : 'error'),
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Terjadi kesalahan.',
            ], $statusCode);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $debug = (bool) config('app.debug');

            return response()->json([
                'status' => 'error',
                'message' => $debug ? $e->getMessage() : 'Terjadi kesalahan pada server.',
            ], 500);
        });
    })->create();
