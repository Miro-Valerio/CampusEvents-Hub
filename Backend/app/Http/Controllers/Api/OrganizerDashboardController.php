<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerEventRequest;
use App\Http\Requests\OrganizerPhotoRequest;
use App\Http\Requests\OrganizerProfileRequest;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\EventReport;
use App\Models\Rsvp;
use App\Notifications\EventCancelled;
use App\Notifications\EventTimeChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class OrganizerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = $request->user()->organizer->events()
            ->withCount([
                'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
                'rsvps as checked_in_count' => fn (Builder $query) => $query->whereNotNull('checked_in_at'),
                'reports as open_reports_count' => fn (Builder $query) => $query->where('status', EventReport::STATUS_OPEN),
            ])
            ->with('photos')
            ->orderByDesc('starts_at')
            ->get();

        $events->each(fn (Event $event) => $event->makeVisible('check_in_code'));

        return response()->json(['data' => $events]);
    }

    public function store(OrganizerEventRequest $request): JsonResponse
    {
        $event = $request->user()->organizer->events()->create($request->validated());
        $event->makeVisible('check_in_code');

        return response()->json($event->loadCount([
            'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
        ]), 201);
    }

    public function update(OrganizerEventRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        if ($event->status === Event::STATUS_CANCELLED) {
            return response()->json(['message' => 'Reactivate this event before editing it.'], 422);
        }

        $previousStartsAt = $event->starts_at?->copy();
        $previousEndsAt = $event->ends_at?->copy();

        $event->update($request->validated());

        if ($event->wasChanged(['starts_at', 'ends_at'])) {
            $event->rsvps()
                ->where('status', Rsvp::STATUS_GOING)
                ->update(['reminder_sent_at' => null]);

            $event->loadMissing('organizer');
            $event->savedBy()
                ->whereKeyNot($request->user()->id)
                ->orderBy('users.id')
                ->chunk(100, function ($users) use ($event, $previousStartsAt, $previousEndsAt): void {
                    Notification::send($users, new EventTimeChanged($event, $previousStartsAt, $previousEndsAt));
                });
        }

        $event = $event->fresh();
        $event->makeVisible('check_in_code');

        return response()->json($event->loadCount([
            'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
        ]));
    }

    public function cancel(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        if ($event->status === Event::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'This event is already cancelled.',
                'event' => $this->eventPayload($event),
            ]);
        }

        $event->update([
            'status' => Event::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'] ?? null,
        ]);

        $event->rsvps()
            ->where('status', Rsvp::STATUS_GOING)
            ->update(['reminder_sent_at' => null]);

        $event->loadMissing('organizer');
        $reason = $event->cancellation_reason;

        $event->rsvps()
            ->with('user')
            ->where('status', Rsvp::STATUS_GOING)
            ->chunkById(100, function ($rsvps) use ($event, $reason): void {
                foreach ($rsvps as $rsvp) {
                    $rsvp->user?->notify(new EventCancelled($event, $reason));
                }
            });

        return response()->json([
            'message' => 'Event cancelled. Everyone who RSVPed has been notified.',
            'event' => $this->eventPayload($event->fresh()),
        ]);
    }

    public function restore(Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $event->update([
            'status' => Event::STATUS_PUBLISHED,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        return response()->json([
            'message' => 'Event reactivated.',
            'event' => $this->eventPayload($event->fresh()),
        ]);
    }

    private function eventPayload(Event $event): Event
    {
        return $event
            ->makeVisible('check_in_code')
            ->loadCount([
                'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
                'rsvps as checked_in_count' => fn (Builder $query) => $query->whereNotNull('checked_in_at'),
                'reports as open_reports_count' => fn (Builder $query) => $query->where('status', EventReport::STATUS_OPEN),
            ]);
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
                'checked_in_at' => $rsvp->checked_in_at,
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
                ->where('status', Event::STATUS_PUBLISHED)
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])
                ->count(),
            'cancelled_events' => $organizer->events()
                ->where('status', Event::STATUS_CANCELLED)
                ->count(),
            'total_rsvps' => $rsvpCount,
            'total_views' => (int) $organizer->events()->sum('views_count'),
            'open_reports' => EventReport::query()
                ->whereIn('event_id', $eventIds)
                ->where('status', EventReport::STATUS_OPEN)
                ->count(),
            'total_check_ins' => Rsvp::query()
                ->whereIn('event_id', $eventIds)
                ->whereNotNull('checked_in_at')
                ->count(),
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
