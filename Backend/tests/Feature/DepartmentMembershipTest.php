<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentMembershipRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DepartmentMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function useToken(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('membership-test')->plainTextToken);
    }

    private function assignOrganizer(Department $department): User
    {
        $organizer = User::factory()->create([
            'role' => 'organizer',
            'department' => $department->code,
            'is_active' => true,
        ]);
        $organizer->organizer()->create([
            'name' => $department->name,
            'description' => $department->description,
            'department_id' => $department->id,
        ]);
        $department->update(['organizer_user_id' => $organizer->id]);

        return $organizer;
    }

    public function test_student_registration_creates_a_pending_request_until_the_designated_organizer_approves(): void
    {
        $department = Department::query()->where('code', 'CCS')->firstOrFail();
        $organizer = $this->assignOrganizer($department);

        $response = $this->postJson('/api/register', [
            'name' => 'New Student',
            'email' => 'new.student@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'department' => $department->code,
        ])->assertCreated()
            ->assertJsonPath('user.department', null)
            ->assertJsonPath('department_request.status', 'pending');

        $requestId = $response->json('department_request.id');
        $student = User::findOrFail($response->json('user.id'));
        $this->assertDatabaseHas('department_membership_requests', [
            'id' => $requestId,
            'user_id' => $student->id,
            'department_id' => $department->id,
            'status' => 'pending',
        ]);

        $this->useToken($organizer)
            ->putJson("/api/organizer/department-membership-requests/{$requestId}", ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'department' => $department->code]);
    }

    public function test_user_endpoint_returns_the_designated_department_and_organizer_profile(): void
    {
        $department = Department::query()->where('code', 'CCS')->firstOrFail();
        $organizer = $this->assignOrganizer($department);

        $this->useToken($organizer)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('department_record.organizer_user_id', $organizer->id)
            ->assertJsonPath('organizer.id', $organizer->organizer->id);
    }

    public function test_faculty_can_join_a_department_without_approval(): void
    {
        $department = Department::query()->where('code', 'CCS')->firstOrFail();
        $faculty = User::factory()->create(['role' => 'faculty', 'department' => null]);

        $this->useToken($faculty)
            ->putJson('/api/department-membership', ['department_id' => $department->id])
            ->assertOk()
            ->assertJsonPath('data.department', $department->code);

        $this->assertDatabaseMissing('department_membership_requests', ['user_id' => $faculty->id]);
    }

    public function test_only_the_designated_department_organizer_can_review_its_membership_requests(): void
    {
        $department = Department::query()->where('code', 'CCS')->firstOrFail();
        $otherDepartment = Department::query()->where('code', 'COE')->firstOrFail();
        $otherOrganizer = $this->assignOrganizer($otherDepartment);
        $student = User::factory()->create(['role' => 'student', 'department' => null]);
        $membershipRequest = DepartmentMembershipRequest::create([
            'user_id' => $student->id,
            'department_id' => $department->id,
            'status' => 'pending',
        ]);

        $this->useToken($otherOrganizer)
            ->putJson("/api/organizer/department-membership-requests/{$membershipRequest->id}", ['action' => 'approve'])
            ->assertNotFound();

        $this->assertDatabaseHas('department_membership_requests', [
            'id' => $membershipRequest->id,
            'status' => 'pending',
        ]);
    }
}
