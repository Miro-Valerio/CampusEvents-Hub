<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerAnnouncementRequest;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class OrganizerAnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $announcements = $request->user()->organizer->announcements()
            ->with('organizer:id,name')
            ->withCount(['reactions', 'reads'])
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $announcements]);
    }

    public function store(OrganizerAnnouncementRequest $request): JsonResponse
    {
        $organizer = $request->user()->organizer;
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['pinned'] = $request->boolean('pinned');

        $announcement = $organizer->announcements()->create($data);
        $announcement->load('organizer:id,name');

        $this->notifyAudience($announcement, $request->user());

        return response()->json($announcement, 201);
    }

    public function update(OrganizerAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('update', $announcement);
        $data = $request->validated();
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
        User::query()
            ->whereKeyNot($author->id)
            ->when($announcement->course, fn (Builder $query) => $query->where('course', $announcement->course))
            ->chunkById(100, function ($users) use ($announcement): void {
                Notification::send($users, new AnnouncementPosted($announcement));
            });
    }
}
