<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private const DEPARTMENTS = ['CCS', 'BSCRIM', 'COE', 'CED', 'CAD', 'CBA'];

    public function users(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(['student', 'organizer'])],
        ]);
        $users = User::query()
            ->where('role', $data['role'])
            ->with('organizer:id,user_id,name,description')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'department', 'course']);

        return response()->json(['data' => $users]);
    }

    public function createOrganizer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'department' => ['required', 'string', Rule::in(self::DEPARTMENTS)],
            'organization_name' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'department' => $data['department'],
            ]);
            $user->forceFill(['role' => 'organizer'])->save();
            $user->organizer()->create([
                'name' => $data['organization_name'],
                'description' => $data['description'],
            ]);

            return $user->load('organizer');
        });

        return response()->json(['data' => $user], 201);
    }

    public function promoteStudent(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'department' => ['required', 'string', Rule::in(self::DEPARTMENTS)],
            'organization_name' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:5000'],
        ]);
        if ($user->role !== 'student' || $user->organizer()->exists()) {
            return response()->json(['message' => 'Only student accounts without an organizer profile can be promoted.'], 422);
        }

        $user = DB::transaction(function () use ($data, $user): User {
            $user->forceFill([
                'role' => 'organizer',
                'department' => $data['department'],
            ])->save();
            $user->organizer()->create([
                'name' => $data['organization_name'],
                'description' => $data['description'],
            ]);

            return $user->fresh()->load('organizer');
        });

        return response()->json(['data' => $user]);
    }

    public function reports(): JsonResponse
    {
        $reports = EventReport::query()
            ->with([
                'event:id,title,organizer_id,is_cancelled',
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

    public function reviewReport(Request $request, EventReport $report): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['reviewed', 'dismissed'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $report->update([
            'status' => $data['status'],
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['data' => $report->fresh()->load(['event:id,title', 'user:id,name,email', 'reviewer:id,name'])]);
    }
}
