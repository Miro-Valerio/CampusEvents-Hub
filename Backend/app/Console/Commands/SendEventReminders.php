<?php

namespace App\Console\Commands;

use App\Models\Rsvp;
use App\Notifications\EventReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:send-reminders {--hours=24}')]
#[Description('Send reminder notifications to students registered for upcoming events')]
class SendEventReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $windowStart = now();
        $windowEnd = now()->addHours($hours);
        $sent = 0;

        Rsvp::query()
            ->with(['event.organizer', 'user'])
            ->where('status', Rsvp::STATUS_GOING)
            ->whereNull('reminder_sent_at')
            ->whereHas('event', fn ($query) => $query
                ->whereBetween('starts_at', [$windowStart, $windowEnd])
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()]))
            ->chunkById(100, function ($rsvps) use (&$sent): void {
                foreach ($rsvps as $rsvp) {
                    if (! $rsvp->user || ! $rsvp->event) {
                        continue;
                    }

                    $rsvp->user->notify(new EventReminder($rsvp->event));
                    $rsvp->forceFill(['reminder_sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} event reminder(s).");

        return self::SUCCESS;
    }
}
