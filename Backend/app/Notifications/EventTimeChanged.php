<?php

namespace App\Notifications;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventTimeChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public ?CarbonInterface $previousStartsAt = null,
        public ?CarbonInterface $previousEndsAt = null,
    ) {}

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
        $previous = $this->previousStartsAt?->format('M j, Y g:i A');

        return [
            'type' => 'event_time_changed',
            'event_id' => $this->event->id,
            'title' => 'Time changed: '.$this->event->title,
            'message' => sprintf(
                'The event now starts %s at %s%s.',
                $this->event->starts_at?->format('M j, Y g:i A'),
                $this->event->venue,
                $previous ? " (previously {$previous})" : '',
            ),
            'starts_at' => $this->event->starts_at?->toIso8601String(),
            'ends_at' => $this->event->ends_at?->toIso8601String(),
            'previous_starts_at' => $this->previousStartsAt?->toIso8601String(),
            'previous_ends_at' => $this->previousEndsAt?->toIso8601String(),
            'venue' => $this->event->venue,
            'category' => $this->event->category,
            'organizer' => $this->event->organizer?->name,
        ];
    }
}
