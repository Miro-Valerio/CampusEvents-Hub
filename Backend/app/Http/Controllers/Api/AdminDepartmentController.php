<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminDepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->with('designatedOrganizer:id,name,email,role,department,is_active')
            ->withCount([
                'users',
                'organizers',
                'membershipRequests as pending_requests_count' => fn ($query) => $query->where('status', 'pending'),
            ])
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
            'designatedOrganizer:id,name,email,role,department,is_active',
            'users:id,name,email,role,department,is_active',
            'membershipRequests' => fn ($query) => $query->where('status', 'pending')->with('user:id,name,email,role'),
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
        $department = DB::transaction(function () use ($data): Department {
            $department = Department::create($data + ['is_legacy' => false]);
            $department->organizers()->create([
                'name' => $department->name,
                'description' => $department->description,
            ]);

            return $department;
        });
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
            'organizer_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);
        if (array_key_exists('organizer_user_id', $data) && $data['organizer_user_id'] !== null) {
            $organizer = User::query()->findOrFail($data['organizer_user_id']);
            if ($organizer->role !== 'organizer' || ! $organizer->is_active || $organizer->department !== $department->code) {
                return response()->json([
                    'message' => 'Choose an active organizer assigned to this department.',
                ], 422);
            }
            if (Department::query()
                ->where('organizer_user_id', $organizer->id)
                ->where('id', '!=', $department->id)
                ->exists()) {
                return response()->json([
                    'message' => 'This organizer is already assigned to another department.',
                ], 422);
            }
        }

        $before = $department->only(['name', 'description', 'is_active', 'organizer_user_id']);
        $department->update($data);
        if (! $department->organizers()->exists()) {
            $department->organizers()->create([
                'name' => $department->name,
                'description' => $department->description,
            ]);
        }
        $activity->record($request->user(), 'department.updated', 'department', $department->id, "Updated department {$department->code}.", [
            'before' => $before,
            'after' => $department->only(array_keys($before)),
        ]);

        return response()->json(['data' => $department]);
    }

    public function destroy(Request $request, Department $department, AdminActivityLogger $activity): JsonResponse
    {
        $userCount = $department->users()->count();
        $requestCount = $department->membershipRequests()->count();
        $announcementCount = Announcement::withTrashed()->where('department_id', $department->id)->count();
        $hasEventHistory = $department->organizers()
            ->whereHas('events', fn ($query) => $query->withTrashed())
            ->exists();
        $hasOrganizerAnnouncements = $department->organizers()
            ->whereHas('announcements', fn ($query) => $query->withTrashed())
            ->exists();
        if ($userCount || $requestCount || $announcementCount || $hasEventHistory || $hasOrganizerAnnouncements
            || $department->organizer_user_id !== null) {
            return response()->json([
                'message' => 'This department still has an assigned organizer, members, membership requests, events, or announcements. Reassign them before deleting it.',
                'users' => $userCount,
                'membership_requests' => $requestCount,
                'announcements' => $announcementCount,
            ], 409);
        }

        DB::transaction(function () use ($department): void {
            $department->organizers()->delete();
            $department->delete();
        });
        $activity->record($request->user(), 'department.deleted', 'department', $department->id, "Deleted department {$department->code}.");

        return response()->json(['message' => 'Department deleted.']);
    }
}
