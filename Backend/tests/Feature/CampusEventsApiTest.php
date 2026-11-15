<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventReport;
use App\Models\Organizer;
use App\Models\Rsvp;
use App\Models\User;
use Database\Seeders\AdminAccountSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampusEventsApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createOrganizer(string $email = 'organizer@example.com'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $user->role = 'organizer';
        $user->save();
        $organizer = $user->organizer()->create([
            'name' => 'Test Organization',
            'description' => 'Organization description',
        ]);

        return [$user, $organizer];
    }

    private function eventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Organizer Event',
            'description' => 'An event created by an organizer.',
            'poster_url' => 'https://example.com/poster.jpg',
            'venue' => 'SPC Hall',
            'starts_at' => now()->addDays(5)->toDateTimeString(),
            'ends_at' => now()->addDays(5)->addHours(2)->toDateTimeString(),
            'category' => 'Workshop',
            'rules' => 'Bring an ID.',
            'capacity' => 50,
        ], $overrides);
    }

    public function test_events_are_returned_as_paginated_json(): void
    {
        $this->seed();

        $response = $this->getJson('/api/events');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'SPC Foundation Week Opening')
            ->assertJsonPath('total', 6);
    }

    public function test_college_organizers_have_linked_demo_accounts(): void
    {
        $this->seed();

        $collegeAccounts = [
            'College of Computer Studies (CCS)' => 'ccs.organizer@spc.edu.ph',
            'College of Business Administration (CBA)' => 'cba.organizer@spc.edu.ph',
            'College of Criminology (CCrim)' => 'ccrim.organizer@spc.edu.ph',
            'College of Engineering (COE)' => 'coe.organizer@spc.edu.ph',
            'College of Education (CED)' => 'ced.organizer@spc.edu.ph',
        ];

        foreach ($collegeAccounts as $organizerName => $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame('organizer', $user->role);
            $this->assertSame($organizerName, $user->organizer?->name);

            $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'password',
            ])->assertOk()
                ->assertJsonPath('user.role', 'organizer')
                ->assertJsonPath('user.organizer.name', $organizerName);
        }
    }

    public function test_registration_returns_a_token_and_persists_the_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Campus Student',
            'email' => 'campus.student@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'department' => 'CCS',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'campus.student@example.com')
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.department', 'CCS')
            ->assertJsonMissingPath('user.password')
            ->assertJsonPath('token', fn (string $token): bool => $token !== '');

        $this->assertDatabaseHas('users', [
            'email' => 'campus.student@example.com',
            'department' => 'CCS',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $response->json('user.id'),
            'name' => 'campus-events-web',
        ]);
    }

    public function test_registration_only_creates_student_accounts_and_rejects_organizer_roles(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Event Organizer',
            'email' => 'new.organizer@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'department' => 'CCS',
            'role' => 'organizer',
            'organization_name' => 'Campus Arts Club',
            'description' => 'Arts and culture events.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'organization_name', 'description']);

        $this->postJson('/api/register', [
            'name' => 'Admin Attempt',
            'email' => 'admin.attempt@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'department' => 'CCS',
            'role' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $this->postJson('/api/register', [
            'name' => 'Invalid Department',
            'email' => 'invalid.department@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'department' => 'NOT-A-DEPARTMENT',
        ])->assertUnprocessable()->assertJsonValidationErrors(['department']);

        $this->assertDatabaseMissing('users', ['email' => 'admin.attempt@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'new.organizer@example.com']);
    }

    public function test_login_and_current_user_include_role_and_organizer_profile(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $user->forceFill(['password' => bcrypt('secure-password')])->save();

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secure-password',
        ])->assertOk()
            ->assertJsonPath('user.role', 'organizer')
            ->assertJsonPath('user.organizer.id', $organizer->id);

        $this->withToken($login->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('role', 'organizer')
            ->assertJsonPath('organizer.id', $organizer->id);
    }

    public function test_student_is_forbidden_from_organizer_routes(): void
    {
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/organizer/events')
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/organizer/stats')
            ->assertForbidden();
    }

    public function test_admin_routes_are_restricted_to_admin_accounts(): void
    {
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/users?role=student')
            ->assertForbidden();
    }

    public function test_admin_seeder_preserves_an_existing_password(): void
    {
        config([
            'admin.bootstrap_email' => 'existing.admin@example.com',
            'admin.bootstrap_password' => 'temporary-bootstrap-password',
        ]);
        $admin = User::factory()->create([
            'email' => 'existing.admin@example.com',
            'password' => 'current-admin-password',
        ]);
        $admin->forceFill(['role' => 'admin'])->save();

        app(AdminAccountSeeder::class)->run();

        $this->assertTrue(Hash::check(
            'current-admin-password',
            $admin->fresh()->password,
        ));
    }

    public function test_admin_can_create_and_promote_department_organizers(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $token = $admin->createToken('feature-test')->plainTextToken;
        $student = User::factory()->create(['department' => 'CCS']);

        $this->withToken($token)
            ->postJson('/api/admin/organizers', [
                'name' => 'CCS Faculty',
                'email' => 'ccs.faculty@example.com',
                'password' => 'temporary-password-12',
                'department' => 'CCS',
                'organization_name' => 'CCS Department',
                'description' => 'CCS department events.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'organizer')
            ->assertJsonPath('data.organizer.name', 'CCS Department');

        $this->assertDatabaseHas('users', [
            'email' => 'ccs.faculty@example.com',
            'role' => 'organizer',
            'department' => 'CCS',
        ]);

        $this->withToken($token)
            ->putJson("/api/admin/users/{$student->id}/promote", [
                'department' => 'CCS',
                'organization_name' => 'CCS Student Officers',
                'description' => 'CCS student officers.',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'organizer')
            ->assertJsonPath('data.organizer.name', 'CCS Student Officers');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => 'organizer',
            'department' => 'CCS',
        ]);
    }

    public function test_admin_can_review_event_reports(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $token = $admin->createToken('feature-test')->plainTextToken;
        $student = User::factory()->create();
        [, $organizer] = $this->createOrganizer();
        $event = $organizer->events()->create($this->eventData());
        $report = EventReport::create([
            'event_id' => $event->id,
            'user_id' => $student->id,
            'reason' => 'incorrect_information',
            'details' => 'The venue is incorrect.',
            'status' => 'pending',
        ]);

        $this->withToken($token)
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.event.title', $event->title);

        $this->withToken($token)
            ->putJson("/api/admin/reports/{$report->id}", [
                'status' => 'reviewed',
                'review_notes' => 'Confirmed and corrected.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.reviewer.id', $admin->id);

        $this->assertDatabaseHas('event_reports', [
            'id' => $report->id,
            'status' => 'reviewed',
            'reviewed_by' => $admin->id,
            'review_notes' => 'Confirmed and corrected.',
        ]);
    }

    public function test_users_can_change_password_with_their_current_password(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/profile/password', [
                'current_password' => 'incorrect-password',
                'new_password' => 'a-new-secure-password',
                'new_password_confirmation' => 'a-new-secure-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->withToken($token)
            ->putJson('/api/profile/password', [
                'current_password' => 'original-password',
                'new_password' => 'a-new-secure-password',
                'new_password_confirmation' => 'a-new-secure-password',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check(
            'a-new-secure-password',
            $user->fresh()->password,
        ));
    }

    public function test_organizer_cannot_edit_another_organizers_event(): void
    {
        [$firstUser] = $this->createOrganizer('first.organizer@example.com');
        [, $secondOrganizer] = $this->createOrganizer('second.organizer@example.com');
        $event = $secondOrganizer->events()->create($this->eventData());
        $token = $firstUser->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/organizer/events/{$event->id}", $this->eventData(['title' => 'Unauthorized change']))
            ->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Organizer Event']);
    }

    public function test_organizer_can_create_update_and_delete_an_event(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $token = $user->createToken('feature-test')->plainTextToken;

        $created = $this->withToken($token)
            ->postJson('/api/organizer/events', $this->eventData())
            ->assertCreated()
            ->assertJsonPath('organizer_id', $organizer->id)
            ->assertJsonPath('title', 'Organizer Event');
        $eventId = $created->json('id');
        $this->assertDatabaseHas('events', ['id' => $eventId, 'organizer_id' => $organizer->id]);

        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData([
                'title' => 'Updated Organizer Event',
                'capacity' => 25,
            ]))
            ->assertOk()
            ->assertJsonPath('title', 'Updated Organizer Event')
            ->assertJsonPath('capacity', 25);

        Rsvp::create([
            'user_id' => User::factory()->create()->id,
            'event_id' => $eventId,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);
        Rsvp::create([
            'user_id' => User::factory()->create()->id,
            'event_id' => $eventId,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 2,
        ]);
        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData(['capacity' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData(['capacity' => 2]))
            ->assertOk();

        $event = Event::findOrFail($eventId);
        $event->update(['baseline_registered_count' => 5]);
        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData(['capacity' => 6]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData(['capacity' => 7]))
            ->assertOk();

        $this->withToken($token)
            ->deleteJson("/api/organizer/events/{$eventId}")
            ->assertOk()
            ->assertJsonPath('message', 'Event deleted successfully.');
        $this->assertSoftDeleted('events', ['id' => $eventId]);
    }

    public function test_organizer_event_times_remain_in_philippine_local_time_when_edited(): void
    {
        [$user] = $this->createOrganizer();
        $token = $user->createToken('feature-test')->plainTextToken;
        $eventData = $this->eventData([
            'starts_at' => '2030-01-15T00:50',
            'ends_at' => '2030-01-15T01:00',
        ]);

        $created = $this->withToken($token)
            ->postJson('/api/organizer/events', $eventData)
            ->assertCreated();

        $eventId = $created->json('id');
        $this->assertSame(
            '2030-01-14T16:50:00.000000Z',
            $created->json('starts_at'),
        );
        $this->assertSame(
            '2030-01-14T17:00:00.000000Z',
            $created->json('ends_at'),
        );
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'starts_at' => '2030-01-15 00:50:00',
            'ends_at' => '2030-01-15 01:00:00',
        ]);

        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $eventData)
            ->assertOk()
            ->assertJsonPath('starts_at', '2030-01-14T16:50:00.000000Z')
            ->assertJsonPath('ends_at', '2030-01-14T17:00:00.000000Z');
    }

    public function test_event_detail_views_are_counted_for_the_organizer_dashboard(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $token = $user->createToken('feature-test')->plainTextToken;
        $event = $organizer->events()->create($this->eventData());

        $this->getJson("/api/events/{$event->id}")->assertOk()->assertJsonPath('views_count', 1);
        $this->getJson("/api/events/{$event->id}")->assertOk()->assertJsonPath('views_count', 2);
        $this->withToken($token)
            ->getJson('/api/organizer/stats')
            ->assertOk()
            ->assertJsonPath('total_views', 2);
    }

    public function test_students_can_report_an_event_once_and_organizers_cannot_report_their_own(): void
    {
        [$owner, $organizer] = $this->createOrganizer();
        $ownerToken = $owner->createToken('feature-test')->plainTextToken;
        $event = $organizer->events()->create($this->eventData());
        $student = User::factory()->create();
        $studentToken = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($studentToken)
            ->postJson("/api/events/{$event->id}/reports", [
                'reason' => 'incorrect_information',
                'details' => 'The venue appears to be incorrect.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Thanks. Your report was submitted for staff review.');
        $this->assertDatabaseHas('event_reports', [
            'event_id' => $event->id,
            'user_id' => $student->id,
            'reason' => 'incorrect_information',
            'status' => 'pending',
        ]);

        $this->withToken($studentToken)
            ->postJson("/api/events/{$event->id}/reports", ['reason' => 'duplicate'])
            ->assertStatus(409);

        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)
            ->postJson("/api/events/{$event->id}/reports", ['reason' => 'other'])
            ->assertForbidden();

        $this->assertSame(1, EventReport::query()->count());
    }

    public function test_organizer_cancellation_preserves_event_notifies_attendees_and_removes_it_from_public_lists(): void
    {
        [$owner, $organizer] = $this->createOrganizer();
        $ownerToken = $owner->createToken('feature-test')->plainTextToken;
        $event = $organizer->events()->create($this->eventData());
        $attendee = User::factory()->create();
        $attendeeToken = $attendee->createToken('feature-test')->plainTextToken;
        Rsvp::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);

        $this->withToken($ownerToken)
            ->postJson("/api/organizer/events/{$event->id}/cancel")
            ->assertOk()
            ->assertJsonPath('is_cancelled', true);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'is_cancelled' => true]);
        $this->assertDatabaseHas('rsvps', [
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'status' => Rsvp::STATUS_CANCELLED,
            'seat_number' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $attendee->id,
            'data->kind' => 'event_cancelled',
        ]);
        $this->getJson('/api/events')->assertOk()->assertJsonPath('total', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($attendeeToken)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.data.kind', 'event_cancelled');
    }

    public function test_students_can_update_profile_photo_course_and_notification_preferences(): void
    {
        Storage::fake('public');
        $student = User::factory()->create(['course' => 'BS Information Technology', 'department' => 'CCS']);
        $token = $student->createToken('feature-test')->plainTextToken;
        $response = $this->withToken($token)
            ->post('/api/profile', [
                '_method' => 'PUT',
                'name' => 'Updated Student',
                'course' => 'BS Computer Science',
                'profile_photo' => UploadedFile::fake()->image('profile.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Student')
            ->assertJsonPath('course', 'BS Computer Science')
            ->assertJsonPath('department', 'CCS')
            ->assertJsonPath('profile_photo_url', fn (string $url): bool => str_contains($url, '/storage/user-profiles/'));

        $photoPath = substr((string) parse_url($response->json('profile_photo_url'), PHP_URL_PATH), strlen('/storage/'));
        Storage::disk('public')->assertExists($photoPath);

        $this->withToken($token)
            ->putJson('/api/profile', ['name' => 'Updated Student', 'department' => 'COE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department']);

        $this->withToken($token)
            ->getJson('/api/notification-preferences')
            ->assertOk()
            ->assertJsonPath('preferences.event_reminders', true)
            ->assertJsonPath('preferences.announcements', true);

        $this->withToken($token)
            ->putJson('/api/notification-preferences', [
                'event_reminders' => false,
                'event_changes' => true,
                'announcements' => false,
            ])
            ->assertOk()
            ->assertJsonPath('preferences.event_reminders', false)
            ->assertJsonPath('preferences.announcements', false);
    }

    public function test_organizer_can_upload_and_replace_an_event_poster(): void
    {
        Storage::fake('public');
        [$user] = $this->createOrganizer();
        $token = $user->createToken('feature-test')->plainTextToken;

        $created = $this->withToken($token)
            ->post('/api/organizer/events', array_merge($this->eventData(), [
                'poster' => UploadedFile::fake()->image('first-poster.jpg'),
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('poster_url', fn (string $url): bool => str_contains($url, '/storage/event-posters/'));

        $eventId = $created->json('id');
        $oldPosterPath = substr((string) parse_url($created->json('poster_url'), PHP_URL_PATH), strlen('/storage/'));
        Storage::disk('public')->assertExists($oldPosterPath);

        $updated = $this->withToken($token)
            ->post("/api/organizer/events/{$eventId}", array_merge($this->eventData([
                'title' => 'Event with replacement poster',
            ]), [
                '_method' => 'PUT',
                'poster' => UploadedFile::fake()->image('replacement-poster.png'),
            ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('title', 'Event with replacement poster');

        $newPosterPath = substr((string) parse_url($updated->json('poster_url'), PHP_URL_PATH), strlen('/storage/'));
        $this->assertNotSame($oldPosterPath, $newPosterPath);
        Storage::disk('public')->assertMissing($oldPosterPath);
        Storage::disk('public')->assertExists($newPosterPath);
    }

    public function test_organizer_can_manage_attendees_photos_profile_and_stats(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $token = $user->createToken('feature-test')->plainTextToken;
        $event = $organizer->events()->create($this->eventData([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
            'baseline_registered_count' => 4,
        ]));
        $attendee = User::factory()->create(['name' => 'Campus Attendee']);
        Rsvp::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 5,
        ]);

        $this->withToken($token)
            ->getJson("/api/organizer/events/{$event->id}/attendees")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Campus Attendee')
            ->assertJsonPath('data.0.email', $attendee->email)
            ->assertJsonPath('data.0.seat_number', 5)
            ->assertJsonPath('data.0.status', 'going');

        $photo = $this->withToken($token)
            ->postJson("/api/organizer/events/{$event->id}/photos", [
                'url' => 'https://example.com/event-photo.jpg',
                'caption' => 'Event memories',
            ])
            ->assertCreated()
            ->assertJsonPath('caption', 'Event memories');

        $this->withToken($token)
            ->deleteJson("/api/organizer/photos/{$photo->json('id')}")
            ->assertOk();

        $this->withToken($token)
            ->putJson('/api/organizer/profile', [
                'name' => 'Updated Organization',
                'description' => 'Updated organization profile.',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Organization');

        $this->withToken($token)
            ->getJson('/api/organizer/stats')
            ->assertOk()
            ->assertJsonPath('total_events', 1)
            ->assertJsonPath('upcoming_events', 0)
            ->assertJsonPath('total_rsvps', 1)
            ->assertJsonPath('seats_filled', 5);
    }

    public function test_organizer_cannot_add_photos_to_an_upcoming_event(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $event = $organizer->events()->create($this->eventData());
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/organizer/events/{$event->id}/photos", [
                'url' => 'https://example.com/event-photo.jpg',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }

    public function test_saving_an_event_returns_401_without_a_token(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $response = $this->postJson("/api/events/{$event->id}/save");

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_save_an_event_with_a_bearer_token(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/events/{$event->id}/save");

        $response
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('message', 'Event saved successfully.');
        $this->assertDatabaseHas('event_user_saves', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);

    }

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('feature-test');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_event_feed_counts_active_rsvps_instead_of_demo_counters(): void
    {
        $this->seed();

        $response = $this->getJson('/api/events');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'SPC Foundation Week Opening')
            ->assertJsonPath('data.0.registered_count', 173);
    }

    public function test_authenticated_user_can_rsvp_once_and_cancel(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertCreated()
            ->assertJsonPath('rsvped', true)
            ->assertJsonPath('registered_count', 174)
            ->assertJsonPath('seat_number', 174);

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertOk()
            ->assertJsonPath('registered_count', 174)
            ->assertJsonPath('seat_number', 174);

        $this->withToken($token)
            ->deleteJson("/api/events/{$event->id}/rsvp")
            ->assertOk()
            ->assertJsonPath('rsvped', false)
            ->assertJsonPath('registered_count', 173);

        $this->assertDatabaseHas('rsvps', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);

        $this->withToken($token)
            ->getJson('/api/my-rsvps')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_rsvp_requires_authentication(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $this->postJson("/api/events/{$event->id}/rsvp")
            ->assertUnauthorized();
    }

    public function test_rsvp_seat_is_reused_after_cancellation(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/events/{$event->id}/rsvp");
        $this->withToken($token)->deleteJson("/api/events/{$event->id}/rsvp");

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertCreated()
            ->assertJsonPath('seat_number', 174);
    }

    public function test_my_rsvps_returns_event_details_and_assigned_seat_number(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'student@spc.edu.ph')->firstOrFail();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/my-rsvps')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'SPC Foundation Week Opening')
            ->assertJsonPath('data.0.organizer.name', 'SPC Student Affairs')
            ->assertJsonPath('data.0.seat_number', 169)
            ->assertJsonPath('data.0.registered_count', 173);
    }

    public function test_full_event_rejects_additional_rsvps(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $event->update(['capacity' => 1, 'baseline_registered_count' => 0]);
        $event->rsvps()->delete();
        $event->rsvps()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'going',
            'seat_number' => 1,
        ]);
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertConflict()
            ->assertJsonPath('message', 'This event is full.');

        $this->assertDatabaseMissing('rsvps', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_organizers_index_and_show(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'student@spc.edu.ph',
            'role' => 'student',
        ]);
        $this->assertDatabaseHas('organizers', [
            'name' => 'SPC Student Affairs',
            'user_id' => User::where('email', 'organizer@spc.edu.ph')->value('id'),
        ]);
        $this->assertDatabaseMissing('organizers', ['name' => 'IT Student Organization']);
        $this->assertDatabaseMissing('organizers', ['name' => 'Arts & Culture Guild']);
        $this->assertDatabaseMissing('users', ['email' => 'it.organizer@spc.edu.ph']);

        $this->getJson('/api/organizers')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonMissing(['name' => 'IT Student Organization'])
            ->assertJsonMissing(['name' => 'Arts & Culture Guild']);

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Web Development Workshop']);

        $ccsOrganizerId = Organizer::where('name', 'College of Computer Studies (CCS)')->value('id');
        $studentAffairsId = Organizer::where('name', 'SPC Student Affairs')->value('id');
        $this->assertDatabaseHas('events', [
            'title' => 'Web Development Workshop',
            'organizer_id' => $ccsOrganizerId,
        ]);
        $this->assertDatabaseHas('events', [
            'title' => 'Hackathon: Build for Campus',
            'organizer_id' => $ccsOrganizerId,
        ]);
        $this->assertDatabaseHas('events', [
            'title' => 'Acoustic Night',
            'organizer_id' => $studentAffairsId,
        ]);
        $this->assertDatabaseHas('announcements', [
            'title' => 'BSIT capstone orientation this Friday',
            'organizer_id' => $ccsOrganizerId,
        ]);

        $organizer = Organizer::where('name', 'SPC Student Affairs')->firstOrFail();
        $this->getJson("/api/organizers/{$organizer->id}")
            ->assertOk()
            ->assertJsonPath('name', 'SPC Student Affairs')
            ->assertJsonCount(3, 'events')
            ->assertJsonCount(1, 'past_events');
    }

    public function test_past_events_archive_has_photos(): void
    {
        $this->seed();

        $this->getJson('/api/events/past')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(4, 'data.0.photos');
    }

    public function test_calendar_returns_events_for_month(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $this->getJson('/api/events/calendar?month='.$event->starts_at->format('Y-m'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'SPC Foundation Week Opening']);
    }

    public function test_attendees_are_listed_with_masked_names(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $this->getJson("/api/events/{$event->id}/attendees")
            ->assertOk()
            ->assertJsonPath('total', 173)
            ->assertSee('Juan C.');
    }

    public function test_user_can_toggle_calendar_and_list_saves(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/events/{$event->id}/calendar")
            ->assertOk()->assertJsonPath('in_calendar', true);
        $this->withToken($token)->getJson('/api/my-calendar')
            ->assertOk()->assertJsonPath('data.0.id', $event->id);
        $this->withToken($token)->postJson("/api/events/{$event->id}/calendar")
            ->assertOk()->assertJsonPath('in_calendar', false);

        $this->withToken($token)->postJson("/api/events/{$event->id}/save");
        $this->withToken($token)->getJson('/api/my-saves')
            ->assertOk()->assertJsonPath('data.0.id', $event->id);
    }

    public function test_cannot_rsvp_to_a_past_event(): void
    {
        $this->seed();
        $event = Event::where('title', 'Welcome Back Orientation')->firstOrFail();
        $token = User::factory()->create()->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/events/{$event->id}/rsvp")
            ->assertStatus(422);
    }
}
