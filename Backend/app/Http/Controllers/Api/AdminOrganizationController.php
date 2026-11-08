<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminOrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $organizations = Organizer::query()
            ->with(['department:id,code,name', 'members:id,name,email,role,department,is_active'])
            ->withCount(['events', 'members'])
            ->when(isset($data['department_id']), fn ($query) => $query->where('department_id', $data['department_id']))
            ->when(array_key_exists('active', $data), fn ($query) => $query->where('is_active', $data['active']))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $organizations]);
    }

    public function store(Request $request, AdminActivityLogger $activity): JsonResponse
    {
        $data = $this->validated($request);
        abort_unless(DB::table('departments')->where('id', $data['department_id'])->where('is_active', true)->exists(), 422, 'Select an active department.');
        $organization = DB::transaction(function () use ($data): Organizer {
            $members = $this->members($data['member_ids'] ?? [], (int) $data['department_id']);
            $organization = Organizer::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'department_id' => $data['department_id'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->syncMembers($organization, $members);

            return $organization->load(['department', 'members']);
        });
        $activity->record($request->user(), 'organization.created', 'organization', $organization->id, "Created organization {$organization->name}.");

        return response()->json(['data' => $organization], 201);
    }

    public function show(Organizer $organizer): JsonResponse
    {
        return response()->json(['data' => $organizer->load([
            'department:id,code,name',
            'members:id,name,email,role,department,is_active',
            'events:id,title,organizer_id,starts_at,ends_at,is_cancelled,is_published',
        ])->loadCount(['events', 'members'])]);
    }

    public function update(Request $request, Organizer $organizer, AdminActivityLogger $activity): JsonResponse
    {
        $data = $this->validated($request, $organizer);
        abort_unless(DB::table('departments')->where('id', $data['department_id'])->where('is_active', true)->exists(), 422, 'Select an active department.');
        $before = $organizer->only(['name', 'description', 'logo_url', 'department_id', 'is_active']);
        if ((int) $organizer->department_id !== (int) $data['department_id']
            && ! array_key_exists('member_ids', $data)
            && $organizer->members()->exists()) {
            return response()->json(['message' => 'Update the organization member assignments when moving it to another department.'], 422);
        }
        DB::transaction(function () use ($data, $organizer): void {
            $members = $this->members($data['member_ids'] ?? [], (int) $data['department_id']);
            $organizer->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'department_id' => $data['department_id'],
                'is_active' => $data['is_active'] ?? $organizer->is_active,
            ]);
            if (array_key_exists('member_ids', $data)) {
                $this->syncMembers($organizer, $members);
            }
            if (! $organizer->is_active) {
                $organizer->events()->where('is_published', true)->update(['is_published' => false]);
            }
        });
        $activity->record($request->user(), 'organization.updated', 'organization', $organizer->id, "Updated organization {$organizer->name}.", [
            'before' => $before,
            'after' => $organizer->only(array_keys($before)),
        ]);

        return response()->json(['data' => $organizer->fresh()->load(['department', 'members'])->loadCount(['events', 'members'])]);
    }

    public function destroy(Request $request, Organizer $organizer, AdminActivityLogger $activity): JsonResponse
    {
        if ($organizer->events()->withTrashed()->exists() || $organizer->announcements()->withTrashed()->exists()) {
            return response()->json(['message' => 'This organization has event or announcement history. Deactivate it instead to preserve records.'], 409);
        }

        $name = $organizer->name;
        $organizer->delete();
        $activity->record($request->user(), 'organization.deleted', 'organization', $organizer->id, "Deleted organization {$name}.");

        return response()->json(['message' => 'Organization deleted.']);
    }

    private function validated(Request $request, ?Organizer $organizer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'department_id' => ['required', 'exists:departments,id'],
            'is_active' => ['sometimes', 'boolean'],
            'member_ids' => ['sometimes', 'array', 'max:100'],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, User>
     */
    private function members(array $ids, int $departmentId): Collection
    {
        $members = User::query()->whereIn('id', $ids)->get();
        if ($members->count() !== count(array_unique($ids))) {
            abort(422, 'One or more selected organization members could not be found.');
        }
        foreach ($members as $member) {
            if (! in_array($member->role, ['faculty', 'organizer'], true)
                || ! $member->is_active
                || (int) $member->departmentRecord?->id !== $departmentId) {
                abort(422, 'Organization members must be active faculty or organizers assigned to the same department.');
            }
        }

        return $members;
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function syncMembers(Organizer $organization, Collection $members): void
    {
        $currentOwner = $organization->user;
        if ($currentOwner && ! $members->contains('id', $currentOwner->id)) {
            $organization->user()->dissociate()->save();
        }
        $organization->members()->sync(
            $members->mapWithKeys(fn (User $member): array => [
                $member->id => ['membership_role' => $member->role === 'organizer' ? 'officer' : 'faculty'],
            ])->all(),
        );
        foreach ($members->where('role', 'organizer') as $member) {
            if (! $member->organizer()->exists()) {
                $organization->user()->associate($member)->save();
                break;
            }
        }
    }
}
