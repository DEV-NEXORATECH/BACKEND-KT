<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'approval-center' => \App\Http\Middleware\EnsureApprovalCenterPermission::class,
        ]);
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->api(prepend: [
            \App\Http\Middleware\NormalizeEmptyQueryParameters::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi autentikasi telah berakhir atau token tidak valid. Silakan login kembali.',
                ], 401);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data atau endpoint yang diminta tidak ditemukan.',
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $firstError = collect($e->errors())->flatten()->first();
                return response()->json([
                    'success' => false,
                    'message' => $firstError ?: 'Data yang dikirimkan tidak valid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $sqlState = $e->errorInfo[0] ?? null;
                $driverCode = $e->errorInfo[1] ?? null;

                Log::error('Database query failed while handling an API request.', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'route' => $request->route()?->getName(),
                    'user_id' => $request->user()?->getAuthIdentifier(),
                    'connection' => $e->getConnectionName(),
                    'sql_state' => $sqlState,
                    'driver_code' => $driverCode,
                    'exception' => $e,
                ]);

                // MySQL Foreign Key Constraint violation (1451 / 1452)
                if (str_contains($e->getMessage(), '1451') || str_contains($e->getMessage(), 'foreign key constraint fails')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Operasi ditolak karena data ini masih digunakan oleh transaksi atau modul lain.',
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada database.',
                    'error_code' => 'DATABASE_QUERY_ERROR',
                    'debug' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });
    })->create();
