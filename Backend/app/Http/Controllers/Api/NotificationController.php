<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

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

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->notificationPreferences()]);
    }

    public function updatePreferences(NotificationPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $preferences = array_merge(
            $user->notificationPreferences(),
            $request->validated(),
        );

        $user->update(['notification_preferences' => $preferences]);

        return response()->json([
            'data' => $user->fresh()->notificationPreferences(),
            'message' => 'Notification settings saved.',
        ]);
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
