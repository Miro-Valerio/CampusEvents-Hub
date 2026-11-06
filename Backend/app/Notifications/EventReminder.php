<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification
{
    use Queueable;

    public function __construct(public Event $event) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'event_reminder',
            'event_id' => $this->event->id,
            'title' => 'Reminder: '.$this->event->title,
            'message' => sprintf(
                'Starts %s at %s. You are registered to attend.',
                $this->event->starts_at?->format('M j, Y g:i A'),
                $this->event->venue,
            ),
            'starts_at' => $this->event->starts_at?->toIso8601String(),
            'ends_at' => $this->event->ends_at?->toIso8601String(),
            'venue' => $this->event->venue,
            'category' => $this->event->category,
            'organizer' => $this->event->organizer?->name,
        ];
    }
}
