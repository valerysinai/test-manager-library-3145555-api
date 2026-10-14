<?php

use App\Library\Adapters\Inbound\Http\ApiExceptionRenderer;
use App\Library\Adapters\Inbound\Http\ApiCors;
use App\Library\Adapters\Inbound\Http\SafeExceptionReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->prepend(ApiCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(fn (Throwable $exception) => SafeExceptionReporter::report($exception));
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception): bool => true);
        $exceptions->render(function (Throwable $exception, Request $request) {
            return ApiExceptionRenderer::render($exception, $request);
        });
    })
    ->create();
