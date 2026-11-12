<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventCancelled extends Notification
{
    use Queueable;

    public function __construct(public Event $event, public ?string $reason = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification('event_updates') ? ['database'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'event_cancelled',
            'event_id' => $this->event->id,
            'title' => 'Event cancelled: '.$this->event->title,
            'message' => sprintf(
                'The event scheduled for %s at %s has been cancelled.%s',
                $this->event->starts_at?->format('M j, Y g:i A'),
                $this->event->venue,
                $this->reason ? " Reason: {$this->reason}" : '',
            ),
            'reason' => $this->reason,
            'starts_at' => $this->event->starts_at?->toIso8601String(),
            'ends_at' => $this->event->ends_at?->toIso8601String(),
            'venue' => $this->event->venue,
            'category' => $this->event->category,
            'organizer' => $this->event->organizer?->name,
        ];
    }
}
