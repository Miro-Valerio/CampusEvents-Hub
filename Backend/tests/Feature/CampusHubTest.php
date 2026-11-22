<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Department,Event,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class CampusHubTest extends TestCase {
 use RefreshDatabase;
 protected Department $dept;
 protected function setUp():void {parent::setUp();$this->dept=Department::create(['name'=>'Computer Studies','code'=>'CCS']);}
 private function user(string $role='student',?Department $department=null):User {
  return User::create(['name'=>'Campus Tester','email'=>uniqid('spc_').'@example.test','password'=>'Password123!','role'=>$role,'course'=>'BS IT','department_id'=>($department??$this->dept)->id]);
 }
 private function event(User $creator,int $limit=2):Event {
  return Event::create(['department_id'=>$this->dept->id,'created_by'=>$creator->id,'title'=>'Campus Workshop','description'=>'Hands-on campus workshop.','venue'=>'Main Hall','starts_at'=>now()->addDays(2),'ends_at'=>now()->addDays(2)->addHours(2),'category'=>'Workshop','seat_limit'=>$limit,'status'=>'published','check_in_code'=>'CODE12']);
 }
 public function test_public_can_browse_events():void {
  $e=$this->event($this->user('president'));
  $this->actingAs($this->user(),'sanctum')->getJson('/api/events')->assertOk()->assertJsonFragment(['title'=>'Campus Workshop']);
  $this->actingAs($this->user(),'sanctum')->getJson('/api/events/'.$e->id)->assertOk()->assertJsonMissing(['check_in_code'=>'CODE12']);
 }
 public function test_registration_is_student_only():void {
  $this->postJson('/api/register',['name'=>'New Student','email'=>'student@example.test','password'=>'Password123!','password_confirmation'=>'Password123!','course'=>'BS IT','department_id'=>$this->dept->id,'role'=>'admin'])
   ->assertCreated()->assertJsonPath('user.role','student')->assertJsonStructure(['token']);
 }
 public function test_rsvp_capacity_cancel_and_reuse_seat():void {
  $event=$this->event($this->user('president'),1);
  $first=$this->user();$second=$this->user();
  $this->actingAs($first,'sanctum')->postJson('/api/events/'.$event->id.'/rsvp')->assertCreated()->assertJsonPath('rsvp.seat_no',1);
  $this->actingAs($second,'sanctum')->postJson('/api/events/'.$event->id.'/rsvp')->assertStatus(422);
  $this->actingAs($first,'sanctum')->deleteJson('/api/events/'.$event->id.'/rsvp')->assertOk();
  $this->actingAs($second,'sanctum')->postJson('/api/events/'.$event->id.'/rsvp')->assertCreated()->assertJsonPath('rsvp.seat_no',1);
 }
 public function test_student_cannot_publish_events():void {
  $this->actingAs($this->user(),'sanctum')->postJson('/api/organizer/events',['title'=>'Unauthorized'])->assertForbidden();
 }
 public function test_memberships_restricted_to_registered_department():void {
  $student=$this->user();$other=Department::create(['name'=>'Education','code'=>'EDU']);
  $this->actingAs($student,'sanctum')->postJson('/api/departments/'.$other->id.'/join')->assertForbidden();
  $this->actingAs($student,'sanctum')->postJson('/api/departments/'.$this->dept->id.'/join')->assertCreated()->assertJsonPath('membership.status','pending');
 }
 public function test_only_admin_can_manage_users():void {
  $this->actingAs($this->user(),'sanctum')->getJson('/api/admin/users')->assertForbidden();
  $this->actingAs($this->user('admin'),'sanctum')->getJson('/api/admin/users')->assertOk();
 }
 public function test_officer_needs_explicit_permissions():void {
  $officer=$this->user('officer');
  $this->actingAs($officer,'sanctum')->postJson('/api/organizer/announcements',['title'=>'News','body'=>'Details.'])->assertForbidden();
  $officer->update(['officer_permissions'=>['announcements']]);
  $this->actingAs($officer,'sanctum')->postJson('/api/organizer/announcements',['title'=>'News','body'=>'Details.'])->assertCreated();
 }
 public function test_membership_requests_notify_only_authorized_department_reviewers():void {
  $student=$this->user();$president=$this->user('president');$admin=$this->user('admin');
  $authorized=$this->user('officer');$authorized->update(['officer_permissions'=>['members']]);
  $unauthorized=$this->user('officer');

  $response=$this->actingAs($student,'sanctum')->postJson('/api/departments/'.$this->dept->id.'/join')
   ->assertCreated()->assertJsonPath('membership.status','pending');
  $membershipId=$response->json('membership.id');
  $this->assertDatabaseHas('memberships',['id'=>$membershipId,'status'=>'pending']);
  foreach([$student,$president,$authorized,$admin] as $recipient){
   $this->assertDatabaseHas('user_notifications',['user_id'=>$recipient->id,'type'=>'membership']);
  }
  $this->assertDatabaseMissing('user_notifications',['user_id'=>$unauthorized->id,'title'=>'New membership request']);

  $this->actingAs($unauthorized,'sanctum')->postJson('/api/organizer/memberships/'.$membershipId.'/review',['status'=>'approved'])->assertForbidden();
  $this->actingAs($president,'sanctum')->postJson('/api/organizer/memberships/'.$membershipId.'/review',['status'=>'approved'])
   ->assertOk()->assertJsonPath('status','approved');
  $this->assertDatabaseHas('memberships',['id'=>$membershipId,'status'=>'approved','reviewed_by'=>$president->id]);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Department membership approved']);
  $this->actingAs($student,'sanctum')->getJson('/api/notifications/count')->assertOk()->assertJsonPath('unread',2);
  $this->actingAs($unauthorized,'sanctum')->postJson('/api/organizer/memberships/'.$membershipId.'/remove')->assertForbidden();
  $this->actingAs($president,'sanctum')->postJson('/api/organizer/memberships/'.$membershipId.'/remove')
   ->assertOk()->assertJsonPath('message','Member removed and notified.');
  $this->assertDatabaseHas('memberships',['id'=>$membershipId,'status'=>'removed']);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Department membership removed']);
  $this->actingAs($student,'sanctum')->postJson('/api/departments/'.$this->dept->id.'/join')
   ->assertCreated()->assertJsonPath('membership.status','pending');
  $this->assertDatabaseHas('memberships',['id'=>$membershipId,'status'=>'pending']);
 }
 public function test_event_reports_are_private_deduplicated_and_notify_on_status_changes():void {
  $student=$this->user();$admin=$this->user('admin');$president=$this->user('president');
  $event=$this->event($president);
  $payload=['category'=>'safety','reason'=>'The event details appear to request unsafe personal information.'];

  $response=$this->actingAs($student,'sanctum')->postJson('/api/events/'.$event->id.'/report',$payload)
   ->assertCreated()->assertJsonPath('message','Report submitted for admin review.');
  $reportId=$response->json('report_id');
  $this->assertDatabaseHas('event_reports',['id'=>$reportId,'event_id'=>$event->id,'user_id'=>$student->id,'category'=>'safety','status'=>'pending']);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'type'=>'event_report','title'=>'Report submitted']);
  $adminNotice=\App\Models\UserNotification::where('user_id',$admin->id)->where('type','event_report')->firstOrFail();
  $this->assertStringContainsString($event->title,$adminNotice->message);
  $this->assertStringContainsString('safety',$adminNotice->message);
  $this->assertStringContainsString('#'.$reportId,$adminNotice->message);
  $this->assertSame('/admin?tab=reports',$adminNotice->link);

  $this->actingAs($student,'sanctum')->postJson('/api/events/'.$event->id.'/report',$payload)->assertOk();
  $this->assertDatabaseCount('event_reports',1);
  $this->assertDatabaseCount('user_notifications',2);
  $this->actingAs($president,'sanctum')->getJson('/api/admin/reports')->assertForbidden();
  $this->actingAs($admin,'sanctum')->getJson('/api/admin/reports')->assertOk()
   ->assertJsonFragment(['category'=>'safety','event_title'=>$event->title]);

  $this->actingAs($admin,'sanctum')->putJson('/api/admin/reports/'.$reportId,['status'=>'under_review'])
   ->assertOk()->assertJsonPath('status','under_review');
  $this->actingAs($admin,'sanctum')->putJson('/api/admin/reports/'.$reportId,['status'=>'resolved'])
   ->assertOk()->assertJsonPath('status','resolved');
  $this->assertDatabaseHas('event_reports',['id'=>$reportId,'status'=>'resolved']);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Event report status updated','message'=>'Your report #'.$reportId.' for '.$event->title.' is now Under review.']);
  $this->actingAs($admin,'sanctum')->putJson('/api/admin/reports/'.$reportId,['status'=>'dismissed'])->assertStatus(422);
 }
 public function test_rsvp_cancellation_and_checkin_notify_participants_and_authorized_officers():void {
  $president=$this->user('president');$student=$this->user();
  $authorized=$this->user('officer');$authorized->update(['officer_permissions'=>['events']]);
  $unauthorized=$this->user('officer');$event=$this->event($president);

  $this->actingAs($student,'sanctum')->postJson('/api/events/'.$event->id.'/rsvp')->assertCreated();
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'RSVP confirmed']);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$authorized->id,'title'=>'New event RSVP']);
  $this->assertDatabaseMissing('user_notifications',['user_id'=>$unauthorized->id,'title'=>'New event RSVP']);
  $this->actingAs($student,'sanctum')->deleteJson('/api/events/'.$event->id.'/rsvp')->assertOk();
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'RSVP cancelled']);
  $this->actingAs($student,'sanctum')->postJson('/api/events/'.$event->id.'/rsvp')->assertCreated();
  $this->assertSame(2,\App\Models\UserNotification::where('user_id',$student->id)->where('title','RSVP confirmed')->count());

  $event->update(['starts_at'=>now()->subMinutes(30),'ends_at'=>now()->addHour()]);
  $this->actingAs($student,'sanctum')->postJson('/api/events/'.$event->id.'/check-in',['code'=>'CODE12'])->assertOk();
  $this->assertDatabaseHas('event_check_ins',['rsvp_id'=>\App\Models\Rsvp::where('user_id',$student->id)->value('id')]);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Attendance recorded']);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$authorized->id,'title'=>'Student checked in']);
  $this->assertDatabaseMissing('user_notifications',['user_id'=>$unauthorized->id,'title'=>'Student checked in']);
 }
 public function test_event_publication_updates_and_cancellation_notify_the_right_audience():void {
  $president=$this->user('president');$authorized=$this->user('officer');$authorized->update(['officer_permissions'=>['events']]);
  $student=$this->user();$otherDepartment=Department::create(['name'=>'Education','code'=>'EDU']);$outsider=$this->user('student',$otherDepartment);
  $start=now()->addDays(4);
  $response=$this->actingAs($president,'sanctum')->postJson('/api/organizer/events',[
   'title'=>'Notification Test Event','description'=>'An event used to verify notification delivery.',
   'venue'=>'Main Hall','category'=>'Workshop','starts_at'=>$start->toDateTimeString(),
   'ends_at'=>$start->copy()->addHours(2)->toDateTimeString(),'seat_limit'=>20,'rules'=>'Bring your student ID.',
  ])->assertCreated();
  $eventId=$response->json('id');
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'New department event']);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$authorized->id,'title'=>'New department event published']);
  $this->assertDatabaseMissing('user_notifications',['user_id'=>$outsider->id,'title'=>'New department event']);

  $this->actingAs($student,'sanctum')->postJson('/api/events/'.$eventId.'/rsvp')->assertCreated();
  $newStart=$start->copy()->addDay();
  $this->actingAs($president,'sanctum')->postJson('/api/organizer/events/'.$eventId,[
   'starts_at'=>$newStart->toDateTimeString(),'ends_at'=>$newStart->copy()->addHours(2)->toDateTimeString(),'venue'=>'Library Hall',
  ])->assertOk();
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Event schedule or venue changed']);
  $this->assertDatabaseHas('user_notifications',['user_id'=>$authorized->id,'title'=>'Event details changed']);

  $this->actingAs($president,'sanctum')->postJson('/api/organizer/events/'.$eventId.'/cancel')->assertOk();
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Event cancelled']);
  $this->assertDatabaseMissing('user_notifications',['user_id'=>$outsider->id,'title'=>'Event cancelled']);
 }
 public function test_announcement_visibility_and_notifications_follow_the_audience():void {
  $president=$this->user('president');$departmentStudent=$this->user();$otherDepartment=Department::create(['name'=>'Business','code'=>'BUS']);
  $outsider=$this->user('student',$otherDepartment);$differentCourse=$this->user('student',$otherDepartment);$differentCourse->update(['course'=>'BS Accounting']);
  $response=$this->actingAs($president,'sanctum')->postJson('/api/organizer/announcements',[
   'title'=>'Department notice','body'=>'A department update for approved students.',
  ])->assertCreated();
  $announcementId=$response->json('id');
  $this->assertDatabaseHas('user_notifications',['user_id'=>$departmentStudent->id,'title'=>'New announcement: Department notice']);
  $this->assertDatabaseMissing('user_notifications',['user_id'=>$outsider->id,'title'=>'New announcement: Department notice']);
  $this->actingAs($outsider,'sanctum')->getJson('/api/announcements')->assertJsonMissing(['title'=>'Department notice']);
  $this->actingAs($outsider,'sanctum')->postJson('/api/announcements/'.$announcementId.'/read')->assertNotFound();

 }
 public function test_role_assignments_department_changes_and_officer_permissions_notify_the_user():void {
  $admin=$this->user('admin');$president=$this->user('president');$student=$this->user();$officer=$this->user('officer');
  $this->actingAs($admin,'sanctum')->putJson('/api/admin/users/'.$student->id,[
   'role'=>'officer','department_id'=>$this->dept->id,
  ])->assertOk()->assertJsonPath('role','officer');
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Role assignment updated']);

  $this->actingAs($president,'sanctum')->putJson('/api/organizer/officers/'.$officer->id.'/permissions',[
   'officer_permissions'=>['events','members'],
  ])->assertOk();
  $this->assertDatabaseHas('user_notifications',['user_id'=>$officer->id,'title'=>'Officer permissions changed']);

  $newDepartment=Department::create(['name'=>'Arts and Sciences','code'=>'CAS']);
  $this->actingAs($admin,'sanctum')->putJson('/api/admin/users/'.$student->id,['department_id'=>$newDepartment->id])->assertOk();
  $this->assertDatabaseHas('user_notifications',['user_id'=>$student->id,'title'=>'Department assignment changed']);
  $this->actingAs($officer,'sanctum')->putJson('/api/organizer/officers/'.$student->id.'/permissions',[
   'officer_permissions'=>['events'],
  ])->assertForbidden();
 }
 public function test_event_reminder_command_sends_one_persistent_reminder_per_rsvp():void {
  $student=$this->user();$event=$this->event($this->user('president'));
  $event->update(['starts_at'=>now()->addHours(6),'ends_at'=>now()->addHours(8)]);
  $this->actingAs($student,'sanctum')->postJson('/api/events/'.$event->id.'/rsvp')->assertCreated();

  \Illuminate\Support\Facades\Artisan::call('events:remind');
  $this->assertSame(1,\App\Models\UserNotification::where('user_id',$student->id)->where('type','reminder')->count());
  $rsvp=\App\Models\Rsvp::where('event_id',$event->id)->where('user_id',$student->id)->firstOrFail();
  $this->assertNotNull($rsvp->reminder_sent_at);
  \Illuminate\Support\Facades\Artisan::call('events:remind');
  $this->assertSame(1,\App\Models\UserNotification::where('user_id',$student->id)->where('type','reminder')->count());
 }
 public function test_notification_center_is_private_and_read_actions_update_unread_count():void {
  $student=$this->user();$other=$this->user();
  $own=\App\Models\UserNotification::create(['user_id'=>$student->id,'type'=>'test','title'=>'Private update','message'=>'Only for this student.','link'=>'/notifications']);
  $private=\App\Models\UserNotification::create(['user_id'=>$other->id,'type'=>'test','title'=>'Other private update','message'=>'Not for this student.']);

  $this->actingAs($student,'sanctum')->getJson('/api/notifications')->assertOk()
   ->assertJsonFragment(['title'=>'Private update'])->assertJsonMissing(['title'=>'Other private update']);
  $this->actingAs($student,'sanctum')->getJson('/api/notifications/count')->assertOk()->assertJsonPath('unread',1);
  $this->actingAs($student,'sanctum')->postJson('/api/notifications/'.$private->id.'/read')->assertForbidden();
  $this->actingAs($student,'sanctum')->postJson('/api/notifications/'.$own->id.'/read')->assertOk();
  $this->actingAs($student,'sanctum')->postJson('/api/notifications/read-all')->assertOk();
  $this->actingAs($student,'sanctum')->getJson('/api/notifications/count')->assertOk()->assertJsonPath('unread',0);
 }
}
