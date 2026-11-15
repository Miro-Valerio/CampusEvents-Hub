<?php

namespace App\Http\Middleware;

use App\Services\OrganizerAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== 'organizer'
            || ! app(OrganizerAccess::class)->query($user)->exists()) {
            return response()->json(['message' => 'Organizer access is required.'], 403);
        }

        return $next($request);
    }
}
