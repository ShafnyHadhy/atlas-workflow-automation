<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $workspace = $request->route('workspace');

        $membership = $workspace->memberships()
            ->where('user_id', $user->id)
            ->exists();

        if (! $membership) {
            abort(403);
        }

        return $next($request); //It passes the request to the next stage of Laravel's request pipeline.
    }
}
