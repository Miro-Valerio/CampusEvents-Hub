<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Rsvp;
use App\Models\User;
use App\Notifications\EventReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->sendDueEventReminders($user);

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]);

        return response()->json([
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    private function sendDueEventReminders(User $user): void
    {
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
        $record->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }
}
