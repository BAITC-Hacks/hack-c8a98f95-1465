<?php

use App\Http\Middleware\DemoIdentity;
use App\Http\Middleware\JsonInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [JsonInput::class]);
        $middleware->alias(['demo' => DemoIdentity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Проверьте введённые данные.',
                    'errors' => $exception->errors(),
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->is('api/*')) {
                $messages = [
                    401 => 'Выберите действующий демопрофиль.',
                    403 => 'Недостаточно прав для этого действия.',
                    404 => 'Ресурс не найден.',
                    405 => 'HTTP-метод не поддерживается.',
                    409 => 'Действие недоступно в текущем состоянии.',
                    429 => 'Слишком много AI-запросов. Повторите позже или обратитесь к администратору.',
                    503 => 'Демонстрационный режим отключён.',
                ];

                return response()->json([
                    'message' => $messages[$exception->getStatusCode()] ?? $exception->getMessage(),
                ], $exception->getStatusCode(), $exception->getHeaders(), JSON_UNESCAPED_UNICODE);
            }
        });
    })->create();
