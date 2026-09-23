<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true) && $request->getContent() !== '') {
            if (! $request->isJson()) {
                abort(415, 'Используйте Content-Type: application/json.');
            }
            try {
                $body = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                abort(400, 'Некорректный JSON.');
            }
            if (! $body instanceof \stdClass) {
                abort(400, 'Тело запроса должно быть JSON-объектом.');
            }
        }

        return $next($request);
    }
}
