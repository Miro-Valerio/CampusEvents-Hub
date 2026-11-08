<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Organizer;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminUserController extends Controller
{
    private const ROLES = ['student', 'faculty', 'organizer', 'admin'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::in(self::ROLES)],
            'department' => ['nullable', 'exists:departments,code'],
            'active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with([
                'departmentRecord:id,code,name',
                'organizer:id,user_id,name,department_id',
                'organizations:id,name,department_id',
                'invitations' => fn ($query) => $query->latest()->limit(1),
            ])
            ->when($data['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($data['department'] ?? null, fn ($query, $department) => $query->where('department', $department))
            ->when(array_key_exists('active', $data), fn ($query) => $query->where('is_active', $data['active']))
            ->when($data['search'] ?? null, function ($query, $search): void {
                $query->where(fn ($users) => $users->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate($data['per_page'] ?? 25);

        return response()->json($users);
    }

    public function store(Request $request, AdminActivityLogger $activity): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'department' => ['required_unless:role,admin', 'nullable', 'exists:departments,code'],
            'course' => ['nullable', 'string', 'max:100'],
            'organization_id' => ['nullable', 'integer', 'exists:organizers,id'],
        ]);
        if (isset($data['department']) && ! DB::table('departments')->where('code', $data['department'])->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Select an active department.'], 422);
        }
        if (($data['role'] ?? null) === 'organizer' && empty($data['organization_id'])) {
            return response()->json(['message' => 'Organizer accounts must be assigned to an organization.'], 422);
        }
        $organization = isset($data['organization_id']) ? Organizer::query()->findOrFail($data['organization_id']) : null;
        if ($organization && (! $organization->is_active || $organization->department?->code !== ($data['department'] ?? null))) {
            return response()->json(['message' => 'The organization must be active and in the selected department.'], 422);
        }

        $user = DB::transaction(function () use ($data, $organization): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
                'role' => $data['role'],
                'department' => $data['department'] ?? null,
                'course' => $data['role'] === 'student' ? ($data['course'] ?? null) : null,
                'is_active' => false,
            ]);
            if ($organization) {
                $organization->members()->attach($user->id, ['membership_role' => 'officer']);
                if ($data['role'] === 'organizer' && ! $organization->user_id) {
                    $organization->user()->associate($user)->save();
                }
            }

            return $user;
        });

        $activity->record($request->user(), 'user.created', 'user', $user->id, "Created {$user->role} account {$user->email}.", [
            'department' => $user->department,
        ]);

        try {
            $this->sendInvitation($user, $request->user()->id);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The account was created but the invitation email could not be sent. Check mail settings and retry the invitation.',
                'data' => $user,
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['data' => $user->load(['departmentRecord', 'organizations'])], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $user->load([
            'departmentRecord',
            'organizer',
            'organizations.department',
            'invitations' => fn ($query) => $query->latest()->limit(5),
        ])]);
    }

    public function update(Request $request, User $user, AdminActivityLogger $activity): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'required', Rule::in(self::ROLES)],
            'department' => ['sometimes', 'nullable', 'exists:departments,code'],
            'course' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['department']) && $data['department'] !== null
            && ! DB::table('departments')->where('code', $data['department'])->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Select an active department.'], 422);
        }
        if ($user->is($request->user()) && (
            (array_key_exists('role', $data) && $data['role'] !== 'admin')
            || (array_key_exists('is_active', $data) && ! $data['is_active'])
        )) {
            return response()->json(['message' => 'You cannot deactivate or remove your own administrator access.'], 422);
        }
        if (array_key_exists('is_active', $data) && $data['is_active'] && ! $user->is_active
            && $user->invitations()->exists()
            && ! $user->invitations()->whereNotNull('used_at')->exists()) {
            return response()->json(['message' => 'This account must complete its password setup invitation before activation.'], 422);
        }
        $removesActiveAdmin = $user->role === 'admin' && $user->is_active
            && (($data['role'] ?? 'admin') !== 'admin' || ! ($data['is_active'] ?? true));
        if ($removesActiveAdmin && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return response()->json(['message' => 'The last active admin account cannot be deactivated or demoted.'], 422);
        }
        $roleAfterUpdate = $data['role'] ?? $user->role;
        if ($roleAfterUpdate !== 'admin'
            && array_key_exists('department', $data)
            && $data['department'] === null) {
            return response()->json(['message' => 'Assign a department to this account.'], 422);
        }
        if (array_key_exists('department', $data) && $data['department'] !== $user->department
            && $user->organizations()->where(function ($organizations) use ($data): void {
                $organizations->whereNull('department_id')
                    ->orWhereHas('department', fn ($department) => $department->where('code', '!=', $data['department']));
            })->exists()) {
            return response()->json(['message' => 'Reassign this user’s organizations before changing their department.'], 422);
        }
        if ($roleAfterUpdate === 'organizer'
            && ! $user->organizer()->exists()
            && ! $user->organizations()->exists()) {
            return response()->json(['message' => 'Assign an organization before changing this account to organizer.'], 422);
        }
        if ($roleAfterUpdate !== 'admin' && ! ($data['department'] ?? $user->department)) {
            return response()->json(['message' => 'Assign a department to this account.'], 422);
        }
        if (($data['role'] ?? $user->role) !== 'student') {
            $data['course'] = null;
        }

        $old = $user->only(['name', 'email', 'role', 'department', 'course', 'is_active']);
        $user->update($data);
        if (! $user->is_active) {
            $user->tokens()->delete();
        }
        if (! in_array($user->role, ['organizer', 'faculty'], true)) {
            $user->organizations()->detach();
            $user->organizer()->update(['user_id' => null]);
        }
        $activity->record($request->user(), 'user.updated', 'user', $user->id, "Updated account {$user->email}.", [
            'before' => $old,
            'after' => $user->only(array_keys($old)),
        ]);

        return response()->json(['data' => $user->fresh()->load(['departmentRecord', 'organizer', 'organizations'])]);
    }

    public function deactivate(Request $request, User $user, AdminActivityLogger $activity): JsonResponse
    {
        if ($user->is($request->user()) || ($user->role === 'admin'
            && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1)) {
            return response()->json(['message' => 'This account cannot be deactivated because it is the current or last active administrator.'], 422);
        }

        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();
        $activity->record($request->user(), 'user.deactivated', 'user', $user->id, "Deactivated account {$user->email}.");

        return response()->json(['message' => 'Account deactivated. Historical registrations and attendance are preserved.']);
    }

    public function resendInvitation(Request $request, User $user, AdminActivityLogger $activity): JsonResponse
    {
        if ($user->is_active) {
            return response()->json(['message' => 'An active account does not need an invitation.'], 422);
        }

        try {
            $this->sendInvitation($user, $request->user()->id);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The invitation email could not be sent. Check mail settings and retry.'], Response::HTTP_BAD_GATEWAY);
        }

        $activity->record($request->user(), 'user.invitation_resent', 'user', $user->id, "Resent the account setup invitation to {$user->email}.");

        return response()->json(['message' => 'A new account setup invitation was sent.']);
    }

    private function sendInvitation(User $user, int $createdBy): void
    {
        $rawToken = Str::random(64);
        $user->invitations()->whereNull('used_at')->update(['expires_at' => now()]);
        $invitation = UserInvitation::create([
            'user_id' => $user->id,
            'created_by' => $createdBy,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDay(),
        ]);
        $setupUrl = rtrim((string) config('app.frontend_url'), '/').'/#setup-account?token='.urlencode($rawToken);

        try {
            Mail::to($user->email)->send(new UserInvitationMail($user, $setupUrl));
        } catch (Throwable $exception) {
            $invitation->delete();
            throw $exception;
        }
    }
}
