<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage in routes: middleware('role:RND') or middleware('role:RND,Admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (! in_array($request->user()->role, $roles, true)) {
            throw new AuthorizationException('Forbidden. Insufficient role.');
        }

        if (! $request->user()->is_active) {
            throw new AuthorizationException('Account is deactivated.');
        }

        return $next($request);
    }
}
