<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Services\AdminActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $announcements = Announcement::query()
            ->with(['organizer:id,name', 'author:id,name,email', 'department:id,code,name'])
            ->withCount(['reads', 'reactions'])
            ->when(isset($data['department_id']), fn ($query) => $query->where('department_id', $data['department_id']))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")))
            ->latest()
            ->paginate($data['per_page'] ?? 25);

        return response()->json($announcements);
    }

    public function store(Request $request, AdminActivityLogger $activity): JsonResponse
    {
        $data = $this->validated($request);
        $departmentId = $data['audience'] === 'department' ? $data['department_id'] : null;
        $announcement = Announcement::create([
            'user_id' => $request->user()->id,
            'organizer_id' => null,
            'department_id' => $departmentId,
            'title' => $data['title'],
            'body' => $data['body'],
            'course' => $data['course'] ?? null,
            'pinned' => $data['pinned'] ?? false,
        ]);
        $announcement->load(['author', 'department']);
        $this->notifyAudience($announcement);
        $activity->record($request->user(), 'announcement.created', 'announcement', $announcement->id, "Published announcement {$announcement->title}.");

        return response()->json(['data' => $announcement], 201);
    }

    public function update(Request $request, int $announcementId, AdminActivityLogger $activity): JsonResponse
    {
        $announcement = Announcement::findOrFail($announcementId);
        $data = $this->validated($request);
        $announcement->update([
            'department_id' => $data['audience'] === 'department' ? $data['department_id'] : null,
            'title' => $data['title'],
            'body' => $data['body'],
            'course' => $data['course'] ?? null,
            'pinned' => $data['pinned'] ?? false,
        ]);
        $activity->record($request->user(), 'announcement.updated', 'announcement', $announcement->id, "Updated announcement {$announcement->title}.");

        return response()->json(['data' => $announcement->fresh()->load(['author', 'department'])]);
    }

    public function destroy(Request $request, int $announcementId, AdminActivityLogger $activity): JsonResponse
    {
        $announcement = Announcement::findOrFail($announcementId);
        $title = $announcement->title;
        $announcement->delete();
        $activity->record($request->user(), 'announcement.deleted', 'announcement', $announcementId, "Deleted announcement {$title}.");

        return response()->json(['message' => 'Announcement removed. Read and reaction history is preserved.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(['campus', 'department'])],
            'department_id' => ['required_if:audience,department', 'nullable', 'exists:departments,id'],
            'course' => ['nullable', 'string', 'max:100'],
            'pinned' => ['sometimes', 'boolean'],
        ]);
        if (($data['audience'] ?? null) === 'department'
            && ! DB::table('departments')->where('id', $data['department_id'])->where('is_active', true)->exists()) {
            abort(422, 'Select an active department.');
        }

        return $data;
    }

    private function notifyAudience(Announcement $announcement): void
    {
        $enabled = DB::table('system_settings')->where('key', 'campus_announcements_enabled')->value('value');
        if ($enabled !== null && json_decode($enabled, true) === false) {
            return;
        }

        User::query()
            ->whereIn('role', ['student', 'faculty'])
            ->where('is_active', true)
            ->whereKeyNot($announcement->user_id)
            ->when($announcement->department_id, fn (Builder $query, int $departmentId) => $query->where(
                'department',
                DB::table('departments')->where('id', $departmentId)->value('code'),
            ))
            ->when($announcement->course, fn (Builder $query, string $course) => $query->where('course', $course))
            ->chunkById(100, function ($users) use ($announcement): void {
                Notification::send(
                    $users->filter(fn (User $user): bool => $user->wantsNotification('announcements')),
                    new AnnouncementPosted($announcement),
                );
            });
    }
}
