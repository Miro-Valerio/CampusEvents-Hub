<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerEventRequest;
use App\Http\Requests\OrganizerPhotoRequest;
use App\Http\Requests\OrganizerProfileRequest;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = $request->user()->organizer->events()
            ->withCount(['rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->with('photos')
            ->orderByDesc('starts_at')
            ->get();

        return response()->json(['data' => $events]);
    }

    public function store(OrganizerEventRequest $request): JsonResponse
    {
        $event = $request->user()->organizer->events()->create($request->validated());

        return response()->json($event->loadCount([
            'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
        ]), 201);
    }

    public function update(OrganizerEventRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);
        $event->update($request->validated());

        return response()->json($event->fresh()->loadCount([
            'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
        ]));
    }

    public function destroy(Event $event): JsonResponse
    {
        Gate::authorize('delete', $event);
        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);
    }

    public function attendees(Event $event): JsonResponse
    {
        Gate::authorize('view', $event);

        $attendees = $event->rsvps()
            ->with('user:id,name,email')
            ->orderBy('seat_number')
            ->get()
            ->map(fn (Rsvp $rsvp): array => [
                'name' => $rsvp->user?->name,
                'email' => $rsvp->user?->email,
                'seat_number' => $rsvp->seat_number,
                'status' => $rsvp->status,
            ])
            ->values();

        return response()->json(['data' => $attendees]);
    }

    public function storePhoto(OrganizerPhotoRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);
        $photo = $event->photos()->create($request->validated());

        return response()->json($photo, 201);
    }

    public function destroyPhoto(EventPhoto $photo): JsonResponse
    {
        Gate::authorize('update', $photo->event);
        $photo->delete();

        return response()->json(['message' => 'Photo deleted successfully.']);
    }

    public function stats(Request $request): JsonResponse
    {
        $organizer = $request->user()->organizer;
        $eventIds = $organizer->events()->select('id');
        $goingRsvps = Rsvp::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', Rsvp::STATUS_GOING);
        $rsvpCount = (clone $goingRsvps)->count();

        return response()->json([
            'total_events' => $organizer->events()->count(),
            'upcoming_events' => $organizer->events()
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])
                ->count(),
            'total_rsvps' => $rsvpCount,
            'seats_filled' => (int) $organizer->events()->sum('baseline_registered_count') + $rsvpCount,
        ]);
    }

    public function updateProfile(OrganizerProfileRequest $request): JsonResponse
    {
        $organizer = $request->user()->organizer;
        $organizer->update($request->validated());

        return response()->json($organizer->fresh());
    }
}
