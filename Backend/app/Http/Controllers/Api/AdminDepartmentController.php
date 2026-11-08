<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->withCount(['users', 'organizers'])
            ->addSelect(['events_count' => DB::table('events')
                ->join('organizers', 'organizers.id', '=', 'events.organizer_id')
                ->selectRaw('COUNT(*)')
                ->whereColumn('organizers.department_id', 'departments.id')
                ->whereNull('events.deleted_at')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $departments]);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json(['data' => $department->load([
            'users:id,name,email,role,department,is_active',
            'organizers:id,name,department_id,is_active',
            'organizers.events:id,title,organizer_id,starts_at,is_cancelled,is_published',
        ])]);
    }

    public function store(Request $request, AdminActivityLogger $activity): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $department = Department::create($data + ['is_legacy' => false]);
        $activity->record($request->user(), 'department.created', 'department', $department->id, "Created department {$department->code}.");

        return response()->json(['data' => $department], 201);
    }

    public function update(Request $request, Department $department, AdminActivityLogger $activity): JsonResponse
    {
        $data = $request->validate([
            'code' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $before = $department->only(['name', 'description', 'is_active']);
        $department->update($data);
        $activity->record($request->user(), 'department.updated', 'department', $department->id, "Updated department {$department->code}.", [
            'before' => $before,
            'after' => $department->only(array_keys($before)),
        ]);

        return response()->json(['data' => $department]);
    }

    public function destroy(Request $request, Department $department, AdminActivityLogger $activity): JsonResponse
    {
        $userCount = $department->users()->count();
        $organizationCount = $department->organizers()->count();
        $announcementCount = Announcement::withTrashed()->where('department_id', $department->id)->count();
        if ($userCount || $organizationCount || $announcementCount) {
            return response()->json([
                'message' => 'This department still has associated users, organizations, or announcements. Reassign them before deleting it.',
                'users' => $userCount,
                'organizations' => $organizationCount,
                'announcements' => $announcementCount,
            ], 409);
        }

        $department->delete();
        $activity->record($request->user(), 'department.deleted', 'department', $department->id, "Deleted department {$department->code}.");

        return response()->json(['message' => 'Department deleted.']);
    }
}
