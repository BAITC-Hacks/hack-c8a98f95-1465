<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoIdentity
{
    public static function customerId(Request $request): ?int
    {
        if (! config('demo.enabled') || $request->header('X-Demo-Role') !== 'customer') {
            return null;
        }

        return collect(config('demo.customers'))->firstWhere('id', $request->header('X-Demo-Id'))['id'] ?? null;
    }

    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(config('demo.enabled'), 503);
        abort_unless(in_array($request->header('X-Demo-Role'), ['customer', 'team'], true), 401);
        abort_unless($request->header('X-Demo-Role') === $role, 403);

        $id = $role === 'customer'
            ? self::customerId($request)
            : (ctype_digit((string) $request->header('X-Demo-Id'))
                ? Team::find($request->header('X-Demo-Id'))?->id : null);
        abort_unless($id, 401);
        $request->attributes->set('demoId', $id);

        return $next($request);
    }
}
