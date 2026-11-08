<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerAnnouncementRequest;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Services\OrganizerAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class OrganizerAnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationIds = app(OrganizerAccess::class)->query($request->user())->select('organizers.id');
        $announcements = Announcement::query()->whereIn('organizer_id', $organizationIds)
            ->with(['organizer:id,name', 'department:id,code,name'])
            ->withCount(['reactions', 'reads'])
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $announcements]);
    }

    public function store(OrganizerAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $organizer = app(OrganizerAccess::class)->resolve($request->user(), $data['organizer_id'] ?? null);
        unset($data['organizer_id']);
        if (! $organizer->department_id) {
            return response()->json(['message' => 'An administrator must assign this organization to a department before posting announcements.'], 422);
        }
        $data['user_id'] = $request->user()->id;
        $data['department_id'] = $organizer->department_id;
        $data['pinned'] = $request->boolean('pinned');

        $announcement = $organizer->announcements()->create($data);
        $announcement->load(['organizer:id,name', 'department:id,code,name']);

        $this->notifyAudience($announcement, $request->user());

        return response()->json($announcement, 201);
    }

    public function update(OrganizerAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('update', $announcement);
        $data = $request->validated();
        unset($data['organizer_id']);
        $data['pinned'] = $request->boolean('pinned');

        $announcement->update($data);

        return response()->json($announcement->fresh()->load('organizer:id,name'));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        Gate::authorize('delete', $announcement);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted successfully.']);
    }

    public function togglePin(Announcement $announcement): JsonResponse
    {
        Gate::authorize('pin', $announcement);
        $announcement->update(['pinned' => ! $announcement->pinned]);

        return response()->json([
            'pinned' => $announcement->pinned,
            'message' => $announcement->pinned
                ? 'Announcement pinned to the top of the feed.'
                : 'Announcement unpinned.',
        ]);
    }

    private function notifyAudience(Announcement $announcement, User $author): void
    {
        $enabled = DB::table('system_settings')->where('key', 'campus_announcements_enabled')->value('value');
        if ($enabled !== null && json_decode($enabled, true) === false) {
            return;
        }
        User::query()
            ->whereIn('role', ['student', 'faculty'])
            ->where('is_active', true)
            ->whereKeyNot($author->id)
            ->when($announcement->department_id, fn (Builder $query, int $id) => $query
                ->where('department', DB::table('departments')->where('id', $id)->value('code')))
            ->when($announcement->course, fn (Builder $query) => $query->where('course', $announcement->course))
            ->chunkById(100, function ($users) use ($announcement): void {
                Notification::send(
                    $users->filter(fn (User $user): bool => $user->wantsNotification('announcements')),
                    new AnnouncementPosted($announcement),
                );
            });
    }
}
