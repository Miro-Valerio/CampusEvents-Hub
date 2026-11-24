<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FacultyController extends Controller
{
    private function departmentId(Request $request): int
    {
        $departmentId = (int) $request->user()->department_id;
        abort_unless($departmentId, 422, 'Your Faculty account must be assigned to a department.');

        return $departmentId;
    }

    private function studentRegistrations(int $departmentId)
    {
        return DB::table('rsvps as registrations')
            ->join('events', 'events.id', '=', 'registrations.event_id')
            ->join('users as students', 'students.id', '=', 'registrations.user_id')
            ->whereNull('events.deleted_at')
            ->where('events.department_id', $departmentId)
            ->where('registrations.status', 'active')
            ->where('students.role', 'student');
    }

    public function dashboard(Request $request)
    {
        $departmentId = $this->departmentId($request);
        $studentRegistrations = $this->studentRegistrations($departmentId);

        return [
            'department' => $request->user()->department()->select('id', 'name', 'code')->first(),
            'stats' => [
                'total_events' => Event::where('department_id', $departmentId)->count(),
                'upcoming_programs' => Event::where('department_id', $departmentId)
                    ->where('status', 'published')
                    ->where('starts_at', '>=', now())
                    ->count(),
                'published_announcements' => Announcement::where('department_id', $departmentId)->count(),
                'student_registrations' => (clone $studentRegistrations)->count(),
                'unique_registered_students' => (clone $studentRegistrations)->distinct()->count('students.id'),
            ],
            'event_options' => Event::where('department_id', $departmentId)
                ->select('id', 'title', 'status')
                ->orderByDesc('starts_at')
                ->get(),
            'events' => Event::where('department_id', $departmentId)
                ->where('status', 'published')
                ->where('ends_at', '>=', now())
                ->withCount(['rsvps as registered_participants_count' => fn ($query) => $query
                    ->where('status', 'active')
                    ->whereHas('user', fn ($user) => $user->where('role', 'student'))])
                ->orderByDesc('starts_at')
                ->paginate(20, ['*'], 'events_page'),
            'announcements' => Announcement::where('department_id', $departmentId)
                ->with('creator:id,name')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get(['id', 'title', 'created_by', 'created_at']),
        ];
    }

    public function participation(Request $request)
    {
        $filters = $request->validate([
            'year_level' => ['nullable', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year'])],
            'event_id' => 'nullable|integer|min:1',
        ]);
        $departmentId = $this->departmentId($request);

        if (!empty($filters['event_id'])) {
            abort_unless(
                Event::where('department_id', $departmentId)->whereKey($filters['event_id'])->exists(),
                404
            );
        }

        $yearLevel = "COALESCE(NULLIF(TRIM(students.year_level), ''), 'Not Set')";
        $attendanceComplete = "COALESCE((attendance.verification_status = 'verified' AND (events.requires_check_out = 0 OR attendance.checked_out_at IS NOT NULL)), 0)";
        $attendanceWindowClosed = "(events.status <> 'cancelled' AND ((events.requires_check_out = 1 AND events.check_out_closes_at IS NOT NULL AND events.check_out_closes_at <= ?) OR (events.requires_check_out = 0 AND events.ends_at <= ?)))";
        $attendanceAbsent = "(attendance.id IS NULL OR (attendance.verification_status = 'verified' AND events.requires_check_out = 1 AND attendance.checked_out_at IS NULL))";
        $query = $this->studentRegistrations($departmentId)
            ->leftJoin('event_check_ins as attendance', 'attendance.rsvp_id', '=', 'registrations.id');

        if (!empty($filters['year_level'])) {
            $query->where('students.year_level', $filters['year_level']);
        }
        if (!empty($filters['event_id'])) {
            $query->where('events.id', $filters['event_id']);
        }

        $now = now();
        $rows = $query
            ->selectRaw("$yearLevel as year_level")
            ->selectRaw('COUNT(registrations.id) as total_registrations')
            ->selectRaw('COUNT(DISTINCT students.id) as unique_students')
            ->selectRaw("SUM(CASE WHEN $attendanceComplete THEN 1 ELSE 0 END) as verified_attendees")
            ->selectRaw("SUM(CASE WHEN $attendanceWindowClosed AND $attendanceAbsent THEN 1 ELSE 0 END) as absent_students", [$now, $now])
            ->selectRaw("SUM(CASE WHEN $attendanceWindowClosed THEN 1 ELSE 0 END) as eligible_confirmed_registrations", [$now, $now])
            ->selectRaw("SUM(CASE WHEN $attendanceWindowClosed AND $attendanceComplete THEN 1 ELSE 0 END) as completed_closed_registrations", [$now, $now])
            ->groupByRaw($yearLevel)
            ->orderByRaw("MIN(CASE $yearLevel WHEN '1st Year' THEN 1 WHEN '2nd Year' THEN 2 WHEN '3rd Year' THEN 3 WHEN '4th Year' THEN 4 ELSE 5 END)")
            ->get()
            ->map(function ($row) {
                foreach (['total_registrations', 'unique_students', 'verified_attendees', 'absent_students', 'eligible_confirmed_registrations', 'completed_closed_registrations'] as $field) {
                    $row->{$field} = (int) $row->{$field};
                }
                $row->attendance_percentage = $row->eligible_confirmed_registrations
                    ? round(100 * $row->completed_closed_registrations / $row->eligible_confirmed_registrations, 1)
                    : null;

                return $row;
            });

        $summary = [
            'total_registrations' => (int) $rows->sum('total_registrations'),
            'unique_students' => (int) $rows->sum('unique_students'),
            'verified_attendees' => (int) $rows->sum('verified_attendees'),
            'absent_students' => (int) $rows->sum('absent_students'),
            'eligible_confirmed_registrations' => (int) $rows->sum('eligible_confirmed_registrations'),
            'completed_closed_registrations' => (int) $rows->sum('completed_closed_registrations'),
        ];
        $summary['attendance_percentage'] = $summary['eligible_confirmed_registrations']
            ? round(100 * $summary['completed_closed_registrations'] / $summary['eligible_confirmed_registrations'], 1)
            : null;
        unset($summary['completed_closed_registrations']);

        return [
            'filters' => [
                'year_level' => $filters['year_level'] ?? null,
                'event_id' => $filters['event_id'] ?? null,
            ],
            'summary' => $summary,
            'by_year_level' => $rows,
        ];
    }
}
