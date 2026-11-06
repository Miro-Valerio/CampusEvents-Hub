<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerEventRequest;
use App\Http\Requests\OrganizerPhotoRequest;
use App\Http\Requests\OrganizerProfileRequest;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Rsvp;
use App\Notifications\EventTimeChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

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
        $previousStartsAt = $event->starts_at?->toIso8601String();
        $previousEndsAt = $event->ends_at?->toIso8601String();
        $event->update($request->validated());
        $event->refresh();

        if (
            $previousStartsAt !== $event->starts_at?->toIso8601String()
            || $previousEndsAt !== $event->ends_at?->toIso8601String()
        ) {
            Notification::send(
                $event->savedBy()->get(),
                new EventTimeChanged($event, $previousStartsAt, $previousEndsAt),
            );
        }

        return response()->json($event->loadCount([
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
                'id' => $rsvp->id,
                'name' => $rsvp->user?->name,
                'email' => $rsvp->user?->email,
                'seat_number' => $rsvp->seat_number,
                'status' => $rsvp->status,
                'checked_in_at' => $rsvp->checked_in_at,
            ])
            ->values();

        return response()->json(['data' => $attendees]);
    }

    public function checkIn(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('view', $event);
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-F0-9]{10}$/i'],
        ]);

        [$rsvp, $alreadyCheckedIn] = DB::transaction(function () use ($event, $data): array {
            $rsvps = $event->rsvps()
                ->where('status', Rsvp::STATUS_GOING)
                ->lockForUpdate()
                ->get();

            $matched = $rsvps->first(
                fn (Rsvp $rsvp): bool => hash_equals($rsvp->checkInCode(), strtoupper($data['code'])),
            );

            if (! $matched) {
                return [null, false];
            }

            if ($matched->checked_in_at) {
                return [$matched, true];
            }

            $matched->forceFill(['checked_in_at' => now()])->save();

            return [$matched->fresh('user'), false];
        });

        if (! $rsvp) {
            return response()->json(['message' => 'No active RSVP matches that check-in code.'], 422);
        }

        if ($alreadyCheckedIn) {
            return response()->json(['message' => 'This attendee is already checked in.'], 409);
        }

        return response()->json([
            'message' => 'Attendee checked in successfully.',
            'data' => [
                'id' => $rsvp->id,
                'name' => $rsvp->user?->name,
                'email' => $rsvp->user?->email,
                'seat_number' => $rsvp->seat_number,
                'status' => $rsvp->status,
                'checked_in_at' => $rsvp->checked_in_at,
            ],
        ]);
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
