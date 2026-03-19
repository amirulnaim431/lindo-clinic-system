<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAppPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasAppPermission') || ! $user->hasAppPermission($permission)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
