<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'organizer' || ! $request->user()->organizer) {
            return response()->json(['message' => 'Organizer access is required.'], 403);
        }

        return $next($request);
    }
}
