<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureStaffOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isStaffOrAdmin()) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}