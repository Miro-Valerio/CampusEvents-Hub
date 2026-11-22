<?php

namespace App\Http\Controllers;

use App\Models\{Announcement, User, UserNotification};
use App\Services\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Announcement::with([
            'department:id,name,code',
            'creator:id,name',
        ])->whereRaw('1=1');

        if ($user && $user->role !== 'admin') {
            $query->where(function ($department) use ($user) {
                $department->whereNull('department_id');
                if ($user->department_id) {
                    $department->orWhere('department_id', $user->department_id);
                }
            });
            if ($user->role !== 'faculty' && $user->course) {
                $query->where(function ($course) use ($user) {
                    $course->whereNull('target_course')
                        ->orWhere('target_course', $user->course);
                });
            } elseif ($user->role !== 'faculty') {
                $query->whereNull('target_course');
            }
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('announcement')) {
            $query->whereKey($request->integer('announcement'));
        }

        $rows = $query->orderByDesc('is_pinned')->latest()->paginate(15);
        $this->decorate($rows->getCollection(), $user);

        return $rows;
    }

    public function managed(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $departmentId = $request->integer('department_id');
            $query = Announcement::query();

            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            return $query->with(['department:id,name,code'])
                ->latest()
                ->get();
        }

        $departmentId = (int) $user->department_id;
        abort_unless($departmentId, 422, 'Choose a department.');
        Hub::canManage($user, 'announcements', $departmentId);

        return Announcement::where('department_id', $departmentId)
            ->with(['department:id,name,code'])
            ->latest()
            ->get();
    }

    public function read(Request $request, Announcement $announcement)
    {
        $this->authorizeReader($request->user(), $announcement);
        DB::table('announcement_reads')->updateOrInsert(
            ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['read_at' => now()]
        );

        return ['message' => 'Marked as read.'];
    }

    public function react(Request $request, Announcement $announcement)
    {
        $this->authorizeReader($request->user(), $announcement);
        $data = $request->validate(['emoji' => ['nullable', Rule::in(['👍', '❤️', '🎉', '🙌'])]]);
        $key = ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id];

        if (!$request->filled('emoji')) {
            DB::table('announcement_reactions')->where($key)->delete();
        } else {
            DB::table('announcement_reactions')->updateOrInsert(
                $key,
                ['emoji' => $data['emoji'], 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return ['message' => 'Reaction updated.'];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:8000',
            'target_course' => 'nullable|string|max:140',
            'department_id' => 'nullable|exists:departments,id',
            'is_pinned' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $departmentId = match ($user->role) {
            'admin' => isset($data['department_id']) ? (int) $data['department_id'] : null,
            default => (int) $user->department_id,
        };

        if ($user->role !== 'admin') {
            Hub::canManage($user, 'announcements', (int) $departmentId);
        }
        return DB::transaction(function () use ($data, $user, $departmentId) {
            $announcement = Announcement::create([
                'department_id' => $departmentId,
                'created_by' => $user->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'target_course' => $data['target_course'] ?? null,
                'is_pinned' => $data['is_pinned'] ?? false,
            ]);

            $message = mb_strimwidth($announcement->body, 0, 150, '...');
            $key = 'announcement:'.$announcement->id.':published';
            if ($departmentId) {
                Hub::notifyDepartmentAudience(
                    $departmentId,
                    $announcement->target_course,
                    'announcement',
                    'New announcement: '.$announcement->title,
                    $message,
                    '/announcements?announcement='.$announcement->id,
                    $key.':audience',
                    $user->id
                );
                Hub::notifyAdmins(
                    'announcement',
                    'New department announcement',
                    $announcement->title,
                    '/announcements?announcement='.$announcement->id,
                    $key.':admins',
                    $user->id
                );
                Hub::notifyDepartmentManagers(
                    $departmentId,
                    'announcements',
                    'announcement',
                    'Department announcement published',
                    $announcement->title,
                    '/announcements?announcement='.$announcement->id,
                    $key.':managers',
                    $user->id
                );
            }
            Hub::log($user, 'announcement.published', 'announcement', $announcement->id);

            return response()->json(
                $announcement->load(['department']),
                201
            );
        });
    }

    public function update(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        $this->authorizeManager($user, $announcement);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'body' => 'sometimes|required|string|max:8000',
            'target_course' => 'nullable|string|max:140',
            'is_pinned' => 'sometimes|boolean',
        ]);
        $announcement->update($data);
        Hub::log($user, 'announcement.updated', 'announcement', $announcement->id);

        return $announcement->fresh()->load(['department']);
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $this->authorizeManager($request->user(), $announcement);
        $id = $announcement->id;
        $announcement->delete();
        Hub::log($request->user(), 'announcement.deleted', 'announcement', $id);

        return ['message' => 'Announcement deleted.'];
    }

    public function notifications(Request $request)
    {
        $perPage = max(1, min(30, $request->integer('per_page', 30)));

        return UserNotification::where('user_id', $request->user()->id)->latest()->paginate($perPage);
    }

    public function markNotification(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return ['message' => 'Read.'];
    }

    public function readAll(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return ['message' => 'All caught up!'];
    }

    public function notificationCount(Request $request)
    {
        return ['unread' => UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count()];
    }

    private function decorate($announcements, User $user): void
    {
        $ids = $announcements->pluck('id');
        $read = DB::table('announcement_reads')
            ->where('user_id', $user->id)->whereIn('announcement_id', $ids)->pluck('announcement_id')->all();
        $react = DB::table('announcement_reactions')
            ->where('user_id', $user->id)->whereIn('announcement_id', $ids)->pluck('emoji', 'announcement_id');
        $counts = DB::table('announcement_reactions')
            ->select('announcement_id', 'emoji', DB::raw('count(*) as total'))
            ->whereIn('announcement_id', $ids)
            ->groupBy('announcement_id', 'emoji')
            ->get()
            ->groupBy('announcement_id');

        $announcements->transform(function ($announcement) use ($read, $react, $counts) {
            $announcement->is_read = in_array($announcement->id, $read);
            $announcement->my_reaction = $react[$announcement->id] ?? null;
            $announcement->reactions = ($counts[$announcement->id] ?? collect())->pluck('total', 'emoji');

            return $announcement;
        });
    }

    private function authorizeReader(User $user, Announcement $announcement): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($announcement->department_id !== null) {
            abort_unless(
                $user->department_id !== null
                    && (int) $announcement->department_id === (int) $user->department_id,
                404
            );
        }

        if ($announcement->target_course !== null) {
            abort_unless(
                $user->course !== null
                    && strcasecmp($announcement->target_course, $user->course) === 0,
                404
            );
        }
    }

    private function authorizeManager(User $user, Announcement $announcement): void
    {
        if ($user->role === 'admin') {
            return;
        }

        Hub::canManage($user, 'announcements', (int) $announcement->department_id);
    }
}
