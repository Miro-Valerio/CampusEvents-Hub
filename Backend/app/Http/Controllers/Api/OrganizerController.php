<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class OrganizerController extends Controller
{
    public function index(): JsonResponse
    {
        $organizers = Organizer::query()
            ->where('is_active', true)
            ->withCount(['events as upcoming_events_count' => fn (Builder $q) => $q
                ->where('is_cancelled', false)
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $organizers]);
    }

    public function show(Organizer $organizer): JsonResponse
    {
        abort_unless($organizer->is_active, 404);
        $going = fn (Builder $q) => $q->where('status', Rsvp::STATUS_GOING);

        $organizer->load([
            'events' => fn ($q) => $q
                ->with('organizer')
                ->withCount(['rsvps as active_rsvps_count' => $going])
                ->where('is_cancelled', false)
                ->where('is_published', true)
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])
                ->orderBy('starts_at'),
        ]);
        $organizer->setRelation('past_events', $organizer->events()
            ->with('organizer')
            ->withCount(['rsvps as active_rsvps_count' => $going])
            ->where('is_cancelled', false)
            ->where('is_published', true)
            ->whereRaw('COALESCE(ends_at, starts_at) < ?', [now()])
            ->orderByDesc('starts_at')
            ->limit(6)
            ->get());

        return response()->json($organizer);
    }
}
