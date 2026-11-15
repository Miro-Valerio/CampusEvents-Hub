<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Department;
use App\Models\Organizer;
use App\Models\Rsvp;
use App\Models\User;
use App\Notifications\EventReminder;
use App\Notifications\EventTimeChanged;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventReminderAndCheckInTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createOrganizer(string $email = 'organizer@example.com'): array
    {
        $department = Department::query()->whereNull('organizer_user_id')->orderBy('id')->firstOrFail();
        $user = User::factory()->create([
            'email' => $email,
            'role' => 'organizer',
            'department' => $department->code,
        ]);
        $organizer = $user->organizer()->create([
            'name' => 'Test Organization',
            'description' => 'Organization description',
            'department_id' => $department->id,
        ]);
        $department->update(['organizer_user_id' => $user->id]);

        return [$user, $organizer];
    }

    private function createEvent(Organizer $organizer, array $overrides = []): Event
    {
        return $organizer->events()->create(array_merge([
            'title' => 'Campus Event',
            'description' => 'An event for students.',
            'venue' => 'SPC Hall',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'category' => 'Workshop',
            'capacity' => 50,
        ], $overrides));
    }

    private function useToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_student_gets_a_private_check_in_code_and_can_view_past_check_ins(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $organizerToken = $organizerUser->createToken('test')->plainTextToken;
        $student = User::factory()->create();
        $studentToken = $student->createToken('test')->plainTextToken;
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(2),
        ]);
        $rsvp = Rsvp::create([
            'user_id' => $student->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);

        $this->useToken($studentToken)
            ->getJson('/api/my-rsvps')
            ->assertOk()
            ->assertJsonPath('data.0.check_in_code', $rsvp->checkInCode());

        $this->useToken($organizerToken)
            ->postJson("/api/organizer/events/{$event->id}/check-in", ['code' => $rsvp->checkInCode()])
            ->assertOk()
            ->assertJsonPath('data.id', $rsvp->id)
            ->assertJsonPath('data.name', $student->name);

        $this->useToken($studentToken)
            ->getJson('/api/my-checkins')
            ->assertOk()
            ->assertJsonPath('data.0.event.id', $event->id)
            ->assertJsonPath('data.0.seat_number', 1);
    }

    public function test_only_the_event_organizer_can_check_in_an_attendee_once(): void
    {
        [$owner, $organizer] = $this->createOrganizer();
        [$otherOrganizer] = $this->createOrganizer('other-organizer@example.com');
        $ownerToken = $owner->createToken('test')->plainTextToken;
        $otherToken = $otherOrganizer->createToken('test')->plainTextToken;
        $event = $this->createEvent($organizer);
        $rsvp = Rsvp::create([
            'user_id' => User::factory()->create()->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);

        $this->useToken($otherToken)
            ->postJson("/api/organizer/events/{$event->id}/check-in", ['code' => $rsvp->checkInCode()])
            ->assertForbidden();

        $this->useToken($ownerToken)
            ->postJson("/api/organizer/events/{$event->id}/check-in", ['code' => 'NOT-A-CODE'])
            ->assertUnprocessable();

        $this->useToken($ownerToken)
            ->postJson("/api/organizer/events/{$event->id}/check-in", ['code' => $rsvp->checkInCode()])
            ->assertOk();

        $this->useToken($ownerToken)
            ->postJson("/api/organizer/events/{$event->id}/check-in", ['code' => $rsvp->checkInCode()])
            ->assertStatus(409);

        $this->assertNotNull($rsvp->fresh()->checked_in_at);
    }

    public function test_rsvp_check_in_code_is_rotated_after_cancellation_and_rejoining(): void
    {
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $student = User::factory()->create();
        $token = $student->createToken('test')->plainTextToken;

        $firstCode = $this->useToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertCreated()
            ->json('check_in_code');

        $this->useToken($token)
            ->deleteJson("/api/events/{$event->id}/rsvp")
            ->assertOk();

        $this->useToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertCreated()
            ->assertJsonPath('check_in_code', fn (string $code): bool => $code !== $firstCode);

        $this->assertNull(Rsvp::query()->where('user_id', $student->id)->firstOrFail()->checked_in_at);
    }

    public function test_saved_users_are_notified_when_event_times_change(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $token = $organizerUser->createToken('test')->plainTextToken;
        $savedUser = User::factory()->create();
        $unsavedUser = User::factory()->create();
        $event = $this->createEvent($organizer);
        $savedUser->savedEvents()->attach($event);
        $previousStart = $event->starts_at;
        $previousEnd = $event->ends_at;

        $this->useToken($token)
            ->putJson("/api/organizer/events/{$event->id}", [
                'title' => $event->title,
                'description' => $event->description,
                'venue' => $event->venue,
                'starts_at' => $event->starts_at->copy()->addHour()->toDateTimeString(),
                'ends_at' => $event->ends_at->copy()->addHour()->toDateTimeString(),
                'category' => $event->category,
                'capacity' => $event->capacity,
            ])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $savedUser->id,
            'type' => EventTimeChanged::class,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $unsavedUser->id,
            'type' => EventTimeChanged::class,
        ]);

        $this->assertSame(
            $previousStart->toIso8601String(),
            $savedUser->notifications()->first()->data['previous_starts_at'],
        );
        $this->assertSame(
            $previousEnd->toIso8601String(),
            $savedUser->notifications()->first()->data['previous_ends_at'],
        );
    }

    public function test_disabled_event_change_preference_suppresses_schedule_notifications(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $organizerToken = $organizerUser->createToken('test')->plainTextToken;
        $savedUser = User::factory()->create();
        $savedUser->forceFill(['notification_preferences' => ['event_changes' => false]])->save();
        $event = $this->createEvent($organizer);
        $savedUser->savedEvents()->attach($event);

        $this->useToken($organizerToken)
            ->putJson("/api/organizer/events/{$event->id}", [
                'title' => $event->title,
                'description' => $event->description,
                'venue' => $event->venue,
                'starts_at' => $event->starts_at->copy()->addHour()->toDateTimeString(),
                'ends_at' => $event->ends_at->copy()->addHour()->toDateTimeString(),
                'category' => $event->category,
                'capacity' => $event->capacity,
            ])
            ->assertOk();

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_id', $savedUser->id)
            ->where('type', EventTimeChanged::class)
            ->count());
    }

    public function test_rsvped_student_gets_one_in_app_reminder_about_an_hour_before_event(): void
    {
        Carbon::setTestNow(now()->startOfMinute());
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHour()->addHours(2),
        ]);
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        Rsvp::create([
            'user_id' => $student->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);
        $studentToken = $student->createToken('test')->plainTextToken;
        $otherToken = $otherStudent->createToken('test')->plainTextToken;
        $this->useToken($studentToken)->getJson('/api/notifications')->assertOk();
        $this->useToken($studentToken)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.data.kind', 'event_reminder')
            ->assertJsonPath('data.0.data.event_id', $event->id);
        $this->useToken($otherToken)->getJson('/api/notifications')->assertOk();

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_id', $student->id)
            ->where('type', EventReminder::class)
            ->count());
        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_id', $otherStudent->id)
            ->where('type', EventReminder::class)
            ->count());
    }

    public function test_disabled_event_reminder_preference_prevents_reminder_delivery(): void
    {
        Carbon::setTestNow(now()->startOfMinute());
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHour()->addHours(2),
        ]);
        $student = User::factory()->create();
        $student->forceFill(['notification_preferences' => ['event_reminders' => false]])->save();
        Rsvp::create([
            'user_id' => $student->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);
        $token = $student->createToken('test')->plainTextToken;

        $this->useToken($token)->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_id', $student->id)
            ->where('type', EventReminder::class)
            ->count());
    }
}
