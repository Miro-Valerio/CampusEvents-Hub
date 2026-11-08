<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Rsvp;
use App\Notifications\EventCancelled;
use App\Notifications\EventTimeChanged;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AdminEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'organization_id' => ['nullable', 'exists:organizers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'trashed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $events = Event::query()
            ->withTrashed((bool) ($data['trashed'] ?? false))
            ->with(['organizer:id,name,department_id', 'organizer.department:id,code,name'])
            ->withCount(['rsvps as registrations_count' => fn ($query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($data['department_id'] ?? null, fn ($query, $id) => $query->whereHas('organizer', fn ($organizers) => $organizers->where('department_id', $id)))
            ->when($data['organization_id'] ?? null, fn ($query, $id) => $query->where('organizer_id', $id))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('starts_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('starts_at', '<=', $to))
            ->orderByDesc('starts_at')
            ->paginate($data['per_page'] ?? 25);

        return response()->json($events);
    }

    public function show(int $eventId): JsonResponse
    {
        $event = Event::withTrashed()->with([
            'organizer.department',
            'rsvps.user:id,name,email,department',
            'photos',
        ])->withCount([
            'rsvps as registrations_count' => fn ($query) => $query->where('status', Rsvp::STATUS_GOING),
            'rsvps as attendance_count' => fn ($query) => $query->whereNotNull('checked_in_at'),
        ])->findOrFail($eventId);

        return response()->json(['data' => $event]);
    }

    public function update(Request $request, int $eventId, AdminActivityLogger $activity): JsonResponse
    {
        $event = Event::withTrashed()->findOrFail($eventId);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:190'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'venue' => ['sometimes', 'required', 'string', 'max:190'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'organizer_id' => ['sometimes', 'required', 'exists:organizers,id'],
            'is_published' => ['sometimes', 'boolean'],
            'is_cancelled' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['organizer_id'])) {
            $organization = DB::table('organizers')->where('id', $data['organizer_id'])->first();
            if (! $organization?->is_active) {
                return response()->json(['message' => 'Events must belong to an active organization.'], 422);
            }
        }
        if (isset($data['capacity'])) {
            $occupied = (int) $event->baseline_registered_count
                + $event->rsvps()->where('status', Rsvp::STATUS_GOING)->count();
            if ($data['capacity'] < $occupied) {
                return response()->json(['message' => "Capacity cannot be lower than the {$occupied} existing registrations."], 422);
            }
        }

        $before = $event->only(['title', 'venue', 'starts_at', 'ends_at', 'organizer_id', 'is_published', 'is_cancelled']);
        $previousStartsAt = $event->starts_at?->toIso8601String();
        $previousEndsAt = $event->ends_at?->toIso8601String();
        $event->update($data);
        if (
            $previousStartsAt !== $event->starts_at?->toIso8601String()
            || $previousEndsAt !== $event->ends_at?->toIso8601String()
        ) {
            $recipients = $event->savedBy()->get()->filter(fn ($user) => $user->wantsNotification('event_changes'));
            Notification::send($recipients, new EventTimeChanged($event, $previousStartsAt, $previousEndsAt));
        }
        if (($before['is_cancelled'] ?? false) === false && $event->is_cancelled) {
            $this->cancelActiveRsvps($event);
        }
        $activity->record($request->user(), 'event.updated', 'event', $event->id, "Updated event {$event->title}.", [
            'before' => $before,
            'after' => $event->only(array_keys($before)),
        ]);

        return response()->json(['data' => $event->fresh()->load('organizer.department')]);
    }

    public function unpublish(Request $request, int $eventId, AdminActivityLogger $activity): JsonResponse
    {
        $event = Event::withTrashed()->findOrFail($eventId);
        $event->update(['is_published' => false]);
        $activity->record($request->user(), 'event.unpublished', 'event', $event->id, "Unpublished event {$event->title}.");

        return response()->json(['message' => 'Event unpublished from Discover.', 'data' => $event]);
    }

    public function destroy(Request $request, int $eventId, AdminActivityLogger $activity): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        DB::transaction(function () use ($event, $request, $activity): void {
            if (! $event->is_cancelled) {
                $this->cancelActiveRsvps($event);
            }
            $event->update(['is_published' => false]);
            $event->delete();
            $activity->record($request->user(), 'event.deleted', 'event', $event->id, "Removed event {$event->title}.");
        });

        return response()->json(['message' => 'Event removed. Registrations and attendance history are preserved.']);
    }

    public function restore(Request $request, int $eventId, AdminActivityLogger $activity): JsonResponse
    {
        $event = Event::onlyTrashed()->findOrFail($eventId);
        $event->restore();
        $activity->record($request->user(), 'event.restored', 'event', $event->id, "Restored event {$event->title} as unpublished.");

        return response()->json(['data' => $event->fresh()]);
    }

    private function cancelActiveRsvps(Event $event): void
    {
        $recipients = $event->rsvps()
            ->where('status', Rsvp::STATUS_GOING)
            ->with('user')
            ->get()
            ->pluck('user')
            ->merge($event->savedBy()->get())
            ->filter()
            ->unique('id')
            ->values();
        $event->rsvps()->where('status', Rsvp::STATUS_GOING)->update([
            'status' => Rsvp::STATUS_CANCELLED,
            'seat_number' => null,
        ]);
        Notification::send(
            $recipients->filter(fn ($user) => $user->wantsNotification('event_changes')),
            new EventCancelled($event),
        );
    }
}
