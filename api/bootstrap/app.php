<?php

use App\Exceptions\Storage\FileRejectedException;
use App\Exceptions\Storage\ObjectNotFoundException;
use App\Exceptions\Storage\PathTraversalException;
use App\Exceptions\Storage\QuotaExceededException;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\AuthenticateApi;
use App\Http\Middleware\ScopedPermission;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'auth.token' => ApiTokenAuth::class,
            'auth.api' => AuthenticateApi::class,
            'scope' => ScopedPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('VALIDATION_ERROR', 'The given data was invalid.', $e->errors(), 422)
            : null);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', status: 401)
            : null);

        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('NOT_FOUND', 'The requested resource was not found.', status: 404)
            : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('NOT_FOUND', 'The requested endpoint does not exist.', status: 404)
            : null);

        $exceptions->render(fn (ObjectNotFoundException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('OBJECT_NOT_FOUND', $e->getMessage(), status: 404)
            : null);

        $exceptions->render(fn (QuotaExceededException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('QUOTA_EXCEEDED', $e->getMessage(), status: 413)
            : null);

        $exceptions->render(fn (FileRejectedException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('FILE_REJECTED', $e->getMessage(), status: 422)
            : null);

        $exceptions->render(fn (PathTraversalException $e, Request $request) => $request->is('api/*')
            ? ApiResponse::error('INVALID_PATH', 'The requested path is invalid.', status: 400)
            : null);
    })->create();
