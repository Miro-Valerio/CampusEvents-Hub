<?php

namespace Tests\Feature;

use App\Mail\UserInvitationMail;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Organizer;
use App\Models\Rsvp;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'department' => null,
            'is_active' => true,
        ]);
    }

    private function useToken(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('admin-test')->plainTextToken);
    }

    public function test_admin_routes_are_forbidden_to_students_and_organizers(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->useToken($student)->getJson('/api/admin/overview')->assertForbidden();

        $organizer = User::factory()->create(['role' => 'organizer']);
        $this->useToken($organizer)->getJson('/api/admin/overview')->assertForbidden();

        $this->useToken($this->admin())
            ->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('total_departments', 5);
    }

    public function test_admin_invites_faculty_with_a_one_time_password_setup_token(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $response = $this->useToken($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Faculty Member',
                'email' => 'faculty@example.com',
                'role' => 'faculty',
                'department' => 'CCS',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'faculty')
            ->assertJsonPath('data.department', 'CCS')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonMissingPath('data.password');

        $userId = $response->json('data.id');
        $user = User::findOrFail($userId);
        $this->assertNotSame('password', $user->password);
        $this->useToken($admin)
            ->putJson("/api/admin/users/{$user->id}", ['is_active' => true])
            ->assertUnprocessable();
        $invitation = UserInvitation::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(64, strlen($invitation->token_hash));

        $rawToken = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$rawToken): bool {
            $fragment = (string) parse_url($mail->setupUrl, PHP_URL_FRAGMENT);
            $query = substr($fragment, strpos($fragment, '?') + 1);
            parse_str($query, $parameters);
            $rawToken = $parameters['token'] ?? null;

            return $mail->user->email === 'faculty@example.com' && is_string($rawToken);
        });
        $this->assertIsString($rawToken);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);

        $this->postJson('/api/account-invitations/complete', [
            'token' => $rawToken,
            'password' => 'FacultySecurePass123!',
            'password_confirmation' => 'FacultySecurePass123!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('FacultySecurePass123!', $user->password));
        $this->assertNotNull($user->email_verified_at);

        $this->postJson('/api/account-invitations/complete', [
            'token' => $rawToken,
            'password' => 'FacultySecurePass123!',
            'password_confirmation' => 'FacultySecurePass123!',
        ])->assertStatus(410);
    }

    public function test_departments_with_assigned_users_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $department = Department::where('code', 'CCS')->firstOrFail();
        $this->useToken($admin)
            ->getJson('/api/admin/departments')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'users_count', 'organizers_count', 'events_count']]]);
        User::factory()->create([
            'role' => 'student',
            'department' => $department->code,
        ]);

        $this->useToken($admin)
            ->deleteJson("/api/admin/departments/{$department->id}")
            ->assertStatus(409)
            ->assertJsonPath('users', 1);

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_admin_can_designate_an_active_organizer_for_a_department(): void
    {
        $admin = $this->admin();
        $department = Department::where('code', 'CCS')->firstOrFail();
        $organizer = User::factory()->create([
            'role' => 'organizer',
            'department' => $department->code,
            'is_active' => true,
        ]);

        $this->useToken($admin)
            ->putJson("/api/admin/departments/{$department->id}", [
                'organizer_user_id' => $organizer->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.organizer_user_id', $organizer->id);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'organizer_user_id' => $organizer->id,
        ]);
    }

    public function test_admin_can_unpublish_an_event_without_an_approval_workflow(): void
    {
        $admin = $this->admin();
        $organizer = Organizer::factory()->create();
        $event = Event::create([
            'title' => 'Published event',
            'description' => 'Event details.',
            'venue' => 'Campus hall',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'category' => 'Workshop',
            'capacity' => 20,
            'organizer_id' => $organizer->id,
            'is_published' => true,
        ]);

        $this->useToken($admin)
            ->postJson("/api/admin/events/{$event->id}/unpublish")
            ->assertOk();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'is_published' => false]);
    }

    public function test_removing_a_reported_event_cancels_registrations_and_records_the_resolution(): void
    {
        $admin = $this->admin();
        $reporter = User::factory()->create();
        $attendee = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $event = Event::create([
            'title' => 'Reported event',
            'description' => 'Event details.',
            'venue' => 'Campus hall',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'category' => 'Workshop',
            'capacity' => 20,
            'organizer_id' => $organizer->id,
            'is_published' => true,
        ]);
        Rsvp::create(['event_id' => $event->id, 'user_id' => $attendee->id, 'status' => Rsvp::STATUS_GOING]);
        $report = EventReport::create([
            'event_id' => $event->id,
            'user_id' => $reporter->id,
            'reason' => 'inappropriate',
            'details' => 'Please review this listing.',
            'status' => 'pending',
        ]);

        $this->useToken($admin)
            ->putJson("/api/admin/reports/{$report->id}", [
                'action' => 'remove',
                'review_notes' => 'Removed after review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.resolution', 'Removed after review.');

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'is_cancelled' => true,
            'is_published' => false,
        ]);
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
        $this->assertDatabaseHas('admin_activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'report.remove',
            'record_id' => $report->id,
        ]);
    }
}
