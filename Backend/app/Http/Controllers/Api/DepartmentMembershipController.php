<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentMembershipRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentMembershipController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => Department::query()
                ->where('is_active', true)
                ->where('is_legacy', false)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendingRequest = $user->departmentMembershipRequests()
            ->where('status', 'pending')
            ->with('department:id,code,name')
            ->latest()
            ->first();

        return response()->json([
            'current_department' => $user->departmentRecord?->only(['id', 'code', 'name']),
            'pending_request' => $pendingRequest,
            'departments' => Department::query()
                ->where('is_active', true)
                ->where('is_legacy', false)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function requestMembership(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403, 'Only students may request department membership.');

        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);
        $department = Department::query()
            ->where('is_active', true)
            ->where('is_legacy', false)
            ->findOrFail($data['department_id']);

        $membershipRequest = DB::transaction(function () use ($request, $department): DepartmentMembershipRequest {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->department === $department->code) {
                abort(422, 'You are already a member of this department.');
            }

            $existing = DepartmentMembershipRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest()
                ->first();
            if ($existing?->department_id === $department->id) {
                return $existing;
            }
            if ($existing) {
                $existing->update(['status' => 'cancelled']);
            }

            return DepartmentMembershipRequest::create([
                'user_id' => $user->id,
                'department_id' => $department->id,
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'message' => 'Your department membership request was submitted.',
            'data' => $membershipRequest->load('department:id,code,name'),
        ], $membershipRequest->wasRecentlyCreated ? 201 : 200);
    }

    public function joinAsFaculty(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'faculty', 403, 'Only faculty members may join a department without approval.');

        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);
        $department = Department::query()
            ->where('is_active', true)
            ->where('is_legacy', false)
            ->findOrFail($data['department_id']);

        DB::transaction(function () use ($request, $department): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->update(['department' => $department->code]);
            $user->departmentMembershipRequests()
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'You joined the department.',
            'data' => $request->user()->fresh()->load('departmentRecord:id,code,name'),
        ]);
    }

    public function requestsForOrganizer(Request $request): JsonResponse
    {
        $department = $this->designatedDepartment($request->user());

        return response()->json([
            'data' => $department->membershipRequests()
                ->where('status', 'pending')
                ->with('user:id,name,email,role,course')
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    public function review(Request $request, DepartmentMembershipRequest $membershipRequest): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
        ]);
        $department = $this->designatedDepartment($request->user());
        abort_unless($membershipRequest->department_id === $department->id, 404);

        DB::transaction(function () use ($membershipRequest, $department, $request, $data): void {
            $locked = DepartmentMembershipRequest::query()
                ->lockForUpdate()
                ->findOrFail($membershipRequest->id);
            if ($locked->status !== 'pending') {
                abort(409, 'This membership request has already been reviewed.');
            }
            if ($data['action'] === 'approve') {
                abort_unless($department->is_active, 422, 'This department is inactive.');
                $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
                abort_unless($user->is_active && $user->role === 'student', 422, 'Only active student accounts can join a department.');
                $user->update(['department' => $department->code]);
            }

            $locked->update([
                'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return response()->json([
            'message' => $data['action'] === 'approve' ? 'Student joined the department.' : 'Membership request rejected.',
            'data' => $membershipRequest->fresh()->load(['user:id,name,email,role,department', 'department:id,code,name']),
        ]);
    }

    private function designatedDepartment(User $user): Department
    {
        abort_unless($user->role === 'organizer', 403, 'Department organizer access is required.');

        return Department::query()
            ->where('organizer_user_id', $user->id)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
