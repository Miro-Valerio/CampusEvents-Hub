<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class OrganizerController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->where('is_legacy', false)
            ->whereHas('organizers', fn ($query) => $query->where('is_active', true)->where('is_listed', true))
            ->with(['organizers' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_listed', true)
                ->orderBy('id')
                ->withCount(['events as upcoming_events_count' => fn (Builder $events) => $events
                    ->where('is_published', true)
                    ->where('is_cancelled', false)
                    ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])])])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description']);
        $organizers = $departments->map(function (Department $department): array {
            $organizer = $department->organizers->first();

            return [
                'id' => $organizer->id,
                'name' => $department->name,
                'description' => $department->description,
                'upcoming_events_count' => (int) $department->organizers->sum('upcoming_events_count'),
            ];
        });

        return response()->json(['data' => $organizers]);
    }

    public function show(Organizer $organizer): JsonResponse
    {
        $department = $organizer->department;
        abort_unless(
            $organizer->is_active && $organizer->is_listed
            && $department?->is_active && ! $department->is_legacy,
            404,
        );
        $going = fn (Builder $q) => $q->where('status', Rsvp::STATUS_GOING);
        $departmentOrganizerIds = $department->organizers()
            ->where('is_active', true)
            ->where('is_listed', true)
            ->select('organizers.id');
        $events = Event::query()->whereIn('organizer_id', $departmentOrganizerIds)
            ->with('organizer')
            ->withCount(['rsvps as active_rsvps_count' => $going])
            ->where('is_cancelled', false)
            ->where('is_published', true)
            ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])
            ->orderBy('starts_at')
            ->get();
        $pastEvents = Event::query()->whereIn('organizer_id', $departmentOrganizerIds)
            ->with('organizer')
            ->withCount(['rsvps as active_rsvps_count' => $going])
            ->where('is_cancelled', false)
            ->where('is_published', true)
            ->whereRaw('COALESCE(ends_at, starts_at) < ?', [now()])
            ->orderByDesc('starts_at')
            ->limit(6)
            ->get();

        return response()->json([
            'id' => $organizer->id,
            'name' => $department->name,
            'description' => $department->description,
            'events' => $events,
            'past_events' => $pastEvents,
        ]);
    }
}
