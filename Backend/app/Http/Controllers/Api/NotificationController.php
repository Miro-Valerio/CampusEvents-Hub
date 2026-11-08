<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Rsvp;
use App\Models\User;
use App\Notifications\EventReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->sendDueEventReminders($user);

        $records = $user->notifications()->latest()->limit(200)->get();
        $visible = $this->visibleRecords($records, $user);
        $notifications = $visible->take(20)
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]);

        return response()->json([
            'data' => $notifications,
            'unread_count' => $this->visibleRecords($user->unreadNotifications()->get(), $user)->count(),
        ]);
    }

    private function sendDueEventReminders(User $user): void
    {
        if (! $user->wantsNotification('event_reminders')) {
            return;
        }

        $now = now();
        $windowStart = $now->copy()->addHour()->subMinute();
        $windowEnd = $now->copy()->addHour();
        $events = Event::query()
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->whereHas('rsvps', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', Rsvp::STATUS_GOING))
            ->get();

        foreach ($events as $event) {
            DB::transaction(function () use ($event, $user): void {
                $inserted = DB::table('event_reminders_sent')->insertOrIgnore([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'starts_at' => $event->starts_at->toDateTimeString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($inserted > 0) {
                    $user->notify(new EventReminder($event));
                }
            });
        }
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        $record = $user->notifications()->whereKey($notification)->firstOrFail();
        abort_unless($this->visibleRecords(collect([$record]), $user)->isNotEmpty(), 404);
        $record->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_count' => $this->visibleRecords($user->unreadNotifications()->get(), $user)->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = $this->visibleRecords($user->unreadNotifications()->get(), $user)->pluck('id');
        $user->notifications()->whereIn('id', $ids)->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $records
     * @return Collection<int, DatabaseNotification>
     */
    private function visibleRecords(Collection $records, User $user): Collection
    {
        $announcementIds = $records
            ->map(fn ($record) => $record->data['announcement_id'] ?? null)
            ->filter()
            ->unique()
            ->values();
        $announcements = Announcement::query()
            ->with('department:id,code')
            ->whereIn('id', $announcementIds)
            ->get()
            ->keyBy('id');

        return $records->filter(function ($record) use ($announcements, $user): bool {
            $announcementId = $record->data['announcement_id'] ?? null;
            if (! $announcementId) {
                return true;
            }
            $announcement = $announcements->get($announcementId);
            if (! $announcement) {
                return false;
            }
            if ($user->role !== 'admin' && $announcement->department_id
                && $announcement->department?->code !== $user->department) {
                return false;
            }

            return ! in_array($user->role, ['student', 'faculty'], true)
                || $announcement->course === null
                || $announcement->course === $user->course;
        })->values();
    }
}
