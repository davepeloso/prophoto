<?php

namespace ProPhoto\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckContextualPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The permission to check
     * @param  string  $contextParam  The route parameter containing the context model
     */
    public function handle(Request $request, Closure $next, string $permission, string $contextParam): Response
    {
        $context = $request->route($contextParam);

        if (!$context) {
            abort(404, 'Resource not found.');
        }

        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthorized.');
        }

        if (!$user->hasContextualPermission($permission, $context)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
