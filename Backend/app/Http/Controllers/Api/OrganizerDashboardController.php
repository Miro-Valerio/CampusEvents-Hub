<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerEventRequest;
use App\Http\Requests\OrganizerPhotoRequest;
use App\Http\Requests\OrganizerProfileRequest;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Rsvp;
use App\Notifications\EventCancelled;
use App\Notifications\EventTimeChanged;
use App\Services\OrganizerAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class OrganizerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationIds = app(OrganizerAccess::class)->query($request->user())->select('organizers.id');
        $events = Event::query()->whereIn('organizer_id', $organizationIds)
            ->withCount(['rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->with('photos')
            ->orderByDesc('starts_at')
            ->get();

        return response()->json(['data' => $events]);
    }

    public function store(OrganizerEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $organizer = app(OrganizerAccess::class)->resolve($request->user(), $data['organizer_id'] ?? null);
        unset($data['organizer_id']);
        unset($data['poster']);
        if ($request->hasFile('poster')) {
            $data['poster_url'] = $this->storePoster($request->file('poster'));
        }

        $event = $organizer->events()->create($data);

        return response()->json($event->loadCount([
            'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
        ]), 201);
    }

    public function update(OrganizerEventRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);
        $oldPosterUrl = $event->poster_url;
        $previousStartsAt = $event->starts_at?->toIso8601String();
        $previousEndsAt = $event->ends_at?->toIso8601String();
        $data = $request->validated();
        unset($data['poster']);
        if ($request->hasFile('poster')) {
            $data['poster_url'] = $this->storePoster($request->file('poster'));
        }
        $event->update($data);
        $event->refresh();
        if ($request->hasFile('poster')) {
            $this->deleteStoredPoster($oldPosterUrl);
        }

        if (
            $previousStartsAt !== $event->starts_at?->toIso8601String()
            || $previousEndsAt !== $event->ends_at?->toIso8601String()
        ) {
            $recipients = $event->savedBy()->get()
                ->filter(fn ($user): bool => $user->wantsNotification('event_changes'));
            Notification::send($recipients, new EventTimeChanged($event, $previousStartsAt, $previousEndsAt));
        }

        return response()->json($event->loadCount([
            'rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING),
        ]));
    }

    public function destroy(Event $event): JsonResponse
    {
        Gate::authorize('delete', $event);
        $posterUrl = $event->poster_url;
        $event->delete();
        $this->deleteStoredPoster($posterUrl);

        return response()->json(['message' => 'Event deleted successfully.']);
    }

    public function cancel(Event $event): JsonResponse
    {
        Gate::authorize('update', $event);
        if ($event->is_cancelled) {
            return response()->json(['message' => 'This event is already cancelled.', 'is_cancelled' => true]);
        }

        $recipients = DB::transaction(function () use ($event) {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            if ($lockedEvent->is_cancelled) {
                return collect();
            }

            $recipients = $lockedEvent->rsvps()
                ->where('status', Rsvp::STATUS_GOING)
                ->with('user')
                ->get()
                ->pluck('user')
                ->merge($lockedEvent->savedBy()->get())
                ->filter()
                ->unique('id')
                ->values();

            $lockedEvent->forceFill(['is_cancelled' => true])->save();
            $lockedEvent->rsvps()
                ->where('status', Rsvp::STATUS_GOING)
                ->update(['status' => Rsvp::STATUS_CANCELLED, 'seat_number' => null]);

            return $recipients;
        });

        $event->refresh();
        Notification::send(
            $recipients->filter(fn ($user): bool => $user->wantsNotification('event_changes')),
            new EventCancelled($event),
        );

        return response()->json([
            'message' => 'Event cancelled. Attendees and saved users have been notified.',
            'is_cancelled' => true,
        ]);
    }

    private function storePoster(UploadedFile $poster): string
    {
        $path = $poster->storePublicly('event-posters', 'public');
        if ($path === false) {
            throw new \RuntimeException('The event poster could not be stored.');
        }

        return Storage::disk('public')->url($path);
    }

    private function deleteStoredPoster(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/storage/event-posters/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }

    public function attendees(Event $event): JsonResponse
    {
        Gate::authorize('view', $event);

        $attendees = $event->rsvps()
            ->where('status', Rsvp::STATUS_GOING)
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
        $organizations = app(OrganizerAccess::class)->query($request->user());
        $eventIds = Event::query()->whereIn('organizer_id', (clone $organizations)->select('organizers.id'))->select('id');
        $goingRsvps = Rsvp::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', Rsvp::STATUS_GOING);
        $rsvpCount = (clone $goingRsvps)->count();
        $events = Event::query()->whereIn('organizer_id', (clone $organizations)->select('organizers.id'));
        $totalViews = (clone $events)->sum('views_count');

        return response()->json([
            'total_events' => (clone $events)->count(),
            'upcoming_events' => (clone $events)
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])
                ->count(),
            'total_rsvps' => $rsvpCount,
            'seats_filled' => (int) (clone $events)->sum('baseline_registered_count') + $rsvpCount,
            'total_views' => (int) $totalViews,
        ]);
    }

    public function updateProfile(OrganizerProfileRequest $request): JsonResponse
    {
        $organizer = app(OrganizerAccess::class)->resolve($request->user());
        $organizer->update($request->validated());

        return response()->json($organizer->fresh());
    }
}
