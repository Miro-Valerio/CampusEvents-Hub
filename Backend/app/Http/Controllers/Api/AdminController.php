<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Rsvp;
use App\Models\User;
use App\Notifications\EventCancelled;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function reports(): JsonResponse
    {
        $reports = EventReport::query()
            ->with([
                'event' => fn ($query) => $query->withTrashed()->select(['id', 'title', 'organizer_id', 'is_cancelled', 'is_published']),
                'event.organizer:id,name',
                'user:id,name,email',
                'reviewer:id,name',
            ])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $reports]);
    }

    public function reviewReport(Request $request, EventReport $report, AdminActivityLogger $activity): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['reviewed', 'dismissed'])],
            'action' => ['nullable', 'string', Rule::in(['review', 'dismiss', 'unpublish', 'remove'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);
        if (empty($data['status']) && empty($data['action'])) {
            return response()->json(['message' => 'Choose a report review action.'], 422);
        }
        $action = $data['action'] ?? ($data['status'] === 'dismissed' ? 'dismiss' : 'review');
        $resolution = trim((string) ($data['resolution'] ?? ''))
            ?: trim((string) ($data['review_notes'] ?? ''));
        if ($resolution === '') {
            return response()->json(['message' => 'Add investigation notes describing the report resolution.'], 422);
        }
        $status = $action === 'dismiss' ? 'dismissed' : 'reviewed';
        DB::transaction(function () use ($action, $data, $report, $request, $activity, $status, $resolution): void {
            $report = EventReport::query()->lockForUpdate()->findOrFail($report->id);
            if (in_array($action, ['unpublish', 'remove'], true)) {
                $event = Event::withTrashed()->lockForUpdate()->findOrFail($report->event_id);
                if ($action === 'unpublish') {
                    $event->update(['is_published' => false]);
                } else {
                    if (! $event->is_cancelled) {
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
                        $event->update(['is_cancelled' => true, 'is_published' => false]);
                        Notification::send(
                            $recipients->filter(fn ($user) => $user->wantsNotification('event_changes')),
                            new EventCancelled($event),
                        );
                    }
                    if (! $event->trashed()) {
                        $event->delete();
                    }
                }
            }
            $report->update([
                'status' => $status,
                'review_notes' => $data['review_notes'] ?? null,
                'resolution' => $resolution,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $activity->record(
                $request->user(),
                "report.{$action}",
                'event_report',
                $report->id,
                "Resolved event report #{$report->id} with action {$action}.",
                ['event_id' => $report->event_id, 'resolution' => $resolution],
            );
        });

        return response()->json(['data' => $report->fresh()->load(['event:id,title', 'user:id,name,email', 'reviewer:id,name'])]);
    }

    public function overview(): JsonResponse
    {
        return response()->json([
            'total_students' => User::where('role', 'student')->count(),
            'total_faculty' => User::where('role', 'faculty')->count(),
            'total_organizers' => User::where('role', 'organizer')->count(),
            'assigned_department_organizers' => Department::whereNotNull('organizer_user_id')->count(),
            'total_departments' => Department::where('is_active', true)->where('is_legacy', false)->count(),
            'total_events' => Event::count(),
            'upcoming_events' => Event::where('is_published', true)
                ->where('is_cancelled', false)
                ->where('starts_at', '>=', now())
                ->count(),
            'total_registrations' => (int) Event::sum('baseline_registered_count')
                + Rsvp::where('status', Rsvp::STATUS_GOING)->count(),
            'pending_reports' => EventReport::where('status', 'pending')->count(),
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);
        $events = Event::query()
            ->where('is_published', true)
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('starts_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('starts_at', '<=', $to))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->whereHas('organizer', fn ($organizers) => $organizers->where('department_id', $id)));
        $eventIds = (clone $events)->select('events.id');
        $registrations = Rsvp::query()->whereIn('event_id', $eventIds)->where('status', Rsvp::STATUS_GOING);
        $departmentEvents = (clone $events)
            ->join('organizers', 'organizers.id', '=', 'events.organizer_id')
            ->leftJoin('departments', 'departments.id', '=', 'organizers.department_id')
            ->selectRaw('departments.id as department_id, departments.code, departments.name, COUNT(events.id) as events_count')
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->orderByDesc('events_count')
            ->get();
        $topEvents = (clone $events)
            ->with('organizer:id,name')
            ->withCount(['rsvps as registrations_count' => fn ($query) => $query->where('status', Rsvp::STATUS_GOING)])
            ->withCount(['rsvps as attendance_count' => fn ($query) => $query->whereNotNull('checked_in_at')])
            ->orderByDesc('attendance_count')
            ->limit(10)
            ->get(['id', 'title', 'organizer_id', 'starts_at', 'baseline_registered_count', 'is_cancelled']);
        return response()->json([
            'totals' => [
                'students' => User::where('role', 'student')->count(),
                'faculty' => User::where('role', 'faculty')->count(),
                'organizers' => User::where('role', 'organizer')->count(),
                'events' => (clone $events)->count(),
                'registrations' => (clone $registrations)->count() + (int) (clone $events)->sum('baseline_registered_count'),
                'attendances' => Rsvp::query()
                    ->whereIn('event_id', $eventIds)
                    ->whereNotNull('checked_in_at')
                    ->count(),
            ],
            'events_per_department' => $departmentEvents,
            'most_attended_events' => $topEvents,
            'department_activity' => $departmentEvents,
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $logs = AdminActivityLog::query()
            ->with('actor:id,name,email')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('summary', 'like', "%{$search}%"))
            ->latest()
            ->paginate($data['per_page'] ?? 50);

        return response()->json($logs);
    }

    public function settings(): JsonResponse
    {
        $defaults = [
            'campus_name' => 'CampusEvents Hub',
            'public_registration_enabled' => true,
            'campus_announcements_enabled' => true,
            'minimum_password_length' => 12,
        ];
        $stored = DB::table('system_settings')->pluck('value', 'key')
            ->map(fn ($value) => json_decode($value, true));

        return response()->json(['data' => array_replace($defaults, $stored->all())]);
    }

    public function updateSettings(Request $request, AdminActivityLogger $activity): JsonResponse
    {
        $data = $request->validate([
            'campus_name' => ['sometimes', 'required', 'string', 'max:120'],
            'public_registration_enabled' => ['sometimes', 'boolean'],
            'campus_announcements_enabled' => ['sometimes', 'boolean'],
            'minimum_password_length' => ['sometimes', 'integer', 'min:12', 'max:128'],
        ]);
        foreach ($data as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
            );
        }
        $activity->record($request->user(), 'settings.updated', 'system_settings', null, 'Updated system settings.', $data);

        return response()->json(['data' => $data]);
    }
}
