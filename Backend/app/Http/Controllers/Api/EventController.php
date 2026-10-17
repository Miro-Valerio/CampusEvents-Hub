<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('organizer')
            ->withCount(['rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()]);
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('venue', 'like', "%{$search}%")
                    ->orWhereHas('organizer', fn ($o) => $o->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->query('date'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('organizer_id')) {
            $query->where('organizer_id', $request->integer('organizer_id'));
        }

        match ($request->query('sort', 'soonest')) {
            'newest' => $query->orderByDesc('created_at'),
            'popular' => $query->orderByRaw('(baseline_registered_count + active_rsvps_count) DESC'),
            default => $query->orderBy('starts_at'),
        };

        return response()->json($query->paginate(12)->withQueryString());
    }

    public function past(Request $request): JsonResponse
    {
        $query = Event::with(['organizer', 'photos'])
            ->withCount(['rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->whereRaw('COALESCE(ends_at, starts_at) < ?', [now()]);
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('venue', 'like', "%{$search}%")
                    ->orWhereHas('organizer', fn ($o) => $o->where('name', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->orderByDesc('starts_at')->paginate(12)->withQueryString());
    }

    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', ($data['month'] ?? now()->format('Y-m')).'-01')->startOfMonth();

        $events = Event::with('organizer:id,name')
            ->whereBetween('starts_at', [$start, $start->copy()->endOfMonth()])
            ->orderBy('starts_at')
            ->get(['id', 'title', 'venue', 'category', 'starts_at', 'ends_at', 'organizer_id', 'poster_url', 'capacity', 'baseline_registered_count']);

        return response()->json(['month' => $start->format('Y-m'), 'data' => $events]);
    }

    public function attendees(Event $event): JsonResponse
    {
        $names = $event->rsvps()
            ->where('status', Rsvp::STATUS_GOING)
            ->with('user:id,name')
            ->orderBy('seat_number')
            ->get()
            ->map(fn (Rsvp $rsvp) => $this->maskName((string) $rsvp->user?->name))
            ->filter()
            ->values();

        return response()->json([
            'data' => $names,
            'baseline_count' => (int) $event->baseline_registered_count,
            'total' => (int) $event->baseline_registered_count + $names->count(),
        ]);
    }

    public function mySaves(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->listFor($request->user()->savedEvents())]);
    }

    public function myCalendar(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->listFor($request->user()->calendarEvents())]);
    }

    public function toggleCalendar(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        if ($user->calendarEvents()->where('events.id', $event->id)->exists()) {
            $user->calendarEvents()->detach($event->id);

            return response()->json(['in_calendar' => false, 'message' => 'Removed from your calendar list.']);
        }
        $user->calendarEvents()->syncWithoutDetaching([$event->id]);

        return response()->json(['in_calendar' => true, 'message' => 'Added to your calendar list.']);
    }

    private function listFor($relation)
    {
        return $relation
            ->with('organizer')
            ->withCount(['rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->orderBy('starts_at')
            ->get();
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }

        return $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json($event
            ->load(['organizer', 'photos'])
            ->loadCount(['rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING)]));
    }

    public function toggleSave(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        $saved = $user->savedEvents()->where('events.id', $event->id)->exists();
        if ($saved) {
            $user->savedEvents()->detach($event->id);

            return response()->json(['saved' => false, 'message' => 'Event removed from saved events.']);
        }
        $user->savedEvents()->syncWithoutDetaching([$event->id]);

        return response()->json(['saved' => true, 'message' => 'Event saved successfully.']);
    }

    public function storeRsvp(Request $request, Event $event): JsonResponse
    {
        if (($event->ends_at ?? $event->starts_at)->isPast()) {
            return response()->json(['message' => 'This event has already ended.'], 422);
        }

        $rsvpResult = DB::transaction(function () use ($event, $request): ?array {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $rsvp = Rsvp::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($rsvp?->status === Rsvp::STATUS_GOING) {
                return [
                    'created' => false,
                    'seat_number' => $rsvp->seat_number,
                    'registered_count' => $lockedEvent->baseline_registered_count
                        + $lockedEvent->rsvps()->where('status', Rsvp::STATUS_GOING)->count(),
                ];
            }

            $activeRsvps = $lockedEvent->rsvps()
                ->where('status', Rsvp::STATUS_GOING)
                ->get(['seat_number']);
            $registeredCount = $lockedEvent->baseline_registered_count + $activeRsvps->count();

            if ($registeredCount >= $lockedEvent->capacity) {
                return null;
            }

            $occupiedSeats = $activeRsvps->pluck('seat_number')->all();
            $seatNumber = $lockedEvent->baseline_registered_count + 1;
            while ($seatNumber <= $lockedEvent->capacity && in_array($seatNumber, $occupiedSeats, true)) {
                $seatNumber++;
            }

            if ($seatNumber > $lockedEvent->capacity) {
                return null;
            }

            if ($rsvp) {
                $rsvp->update([
                    'status' => Rsvp::STATUS_GOING,
                    'seat_number' => $seatNumber,
                ]);

                return [
                    'created' => true,
                    'seat_number' => $seatNumber,
                    'registered_count' => $registeredCount + 1,
                ];
            }

            $lockedEvent->rsvps()->create([
                'user_id' => $request->user()->id,
                'status' => Rsvp::STATUS_GOING,
                'seat_number' => $seatNumber,
            ]);

            return [
                'created' => true,
                'seat_number' => $seatNumber,
                'registered_count' => $registeredCount + 1,
            ];
        });

        if ($rsvpResult === null) {
            return response()->json(['message' => 'This event is full.'], 409);
        }

        return response()->json([
            'rsvped' => true,
            'seat_number' => $rsvpResult['seat_number'],
            'registered_count' => $rsvpResult['registered_count'],
            'message' => $rsvpResult['created'] ? 'You are registered for this event.' : 'You are already registered for this event.',
        ], $rsvpResult['created'] ? 201 : 200);
    }

    public function cancelRsvp(Request $request, Event $event): JsonResponse
    {
        $event->rsvps()
            ->where('user_id', $request->user()->id)
            ->where('status', Rsvp::STATUS_GOING)
            ->update([
                'status' => Rsvp::STATUS_CANCELLED,
                'seat_number' => null,
            ]);

        $registeredCount = $event->baseline_registered_count + $event->rsvps()
            ->where('status', Rsvp::STATUS_GOING)
            ->count();

        return response()->json([
            'rsvped' => false,
            'registered_count' => $registeredCount,
            'message' => 'Your RSVP has been cancelled.',
        ]);
    }

    public function myRsvps(Request $request): JsonResponse
    {
        $events = Event::query()
            ->with('organizer')
            ->withCount(['rsvps as active_rsvps_count' => fn (Builder $query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->whereHas('rsvps', fn (Builder $query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', Rsvp::STATUS_GOING))
            ->with(['rsvps' => fn (HasMany $query) => $query
                ->select('id', 'event_id', 'seat_number')
                ->where('user_id', $request->user()->id)
                ->where('status', Rsvp::STATUS_GOING)])
            ->orderBy('starts_at')
            ->get();

        $events->each(function (Event $event): void {
            $event->setAttribute('seat_number', $event->rsvps->first()?->seat_number);
            $event->unsetRelation('rsvps');
        });

        return response()->json(['data' => $events]);
    }
}
