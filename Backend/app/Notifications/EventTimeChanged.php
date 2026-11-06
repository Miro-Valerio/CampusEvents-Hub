<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventTimeChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public ?string $previousStartsAt,
        public ?string $previousEndsAt,
    ) {}

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
            'kind' => 'event_time_changed',
            'event_id' => $this->event->id,
            'title' => 'Event time changed: '.$this->event->title,
            'message' => 'The event schedule has changed. New start: '.$this->event->starts_at?->toIso8601String(),
            'starts_at' => $this->event->starts_at?->toIso8601String(),
            'ends_at' => $this->event->ends_at?->toIso8601String(),
            'previous_starts_at' => $this->previousStartsAt,
            'previous_ends_at' => $this->previousEndsAt,
        ];
    }
}
