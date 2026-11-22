<?php
namespace App\Http\Controllers;
use App\Models\{Announcement,Department,Event,Membership,User};
use App\Services\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Password};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
class AdminController extends Controller {
 public function stats(){
  return ['students'=>User::where('role','student')->count(),'officers'=>User::whereIn('role',['officer','president'])->count(),
   'departments'=>Department::count(),'events'=>Event::count(),'upcoming'=>Event::where('status','published')->where('starts_at','>=',now())->count(),
   'rsvps'=>DB::table('rsvps')->where('status','active')->count(),'reports'=>DB::table('event_reports')->where('status','pending')->count(),
   'announcements'=>Announcement::count()];
 }
 public function allEvents(){return Event::with(['department:id,name,code'])->withCount(['rsvps as attendee_count'=>fn($q)=>$q->where('status','active')])->latest()->get();}
 public function allAnnouncements(){return Announcement::with(['department:id,name,code'])->latest()->get();}
 public function users(Request $r){
  $q=User::with(['department:id,name,code']);
  if($r->filled('search'))$q->where(fn($s)=>$s->where('name','like','%'.$r->search.'%')->orWhere('email','like','%'.$r->search.'%'));
  return $q->orderBy('name')->paginate(30);
 }
 public function storeFaculty(Request $r){
  $data=$r->validate([
   'name'=>'required|string|max:120',
   'email'=>['required','email','max:255',Rule::unique('users','email'),Rule::unique('users','pending_email')],
   'faculty_position'=>'nullable|string|max:120',
  ]);
  $department=Department::where('code','CCS')->firstOrFail();
  return DB::transaction(function()use($r,$data,$department){
   $user=User::create([
    'name'=>$data['name'],
    'email'=>strtolower($data['email']),
    'password'=>Str::random(64),
    'department_id'=>$department->id,
    'role'=>'faculty',
    'faculty_position'=>$data['faculty_position']??null,
    'is_active'=>true,
   ]);
   $status=Password::sendResetLink(['email'=>$user->email]);
   if($status!==Password::RESET_LINK_SENT){
    throw ValidationException::withMessages(['email'=>__($status)]);
   }
   Hub::log($r->user(),'faculty.created','user',$user->id,['department_id'=>$department->id]);
   return response()->json([
    'message'=>'Faculty account created. Password setup instructions were sent to the email address.',
    'user'=>$user->load(['department']),
   ],201);
  });
 }
 public function updateUser(Request $r,User $user){
  abort_if($r->user()->id===$user->id && ($r->input('role') && $r->input('role')!=='admin'),422,'You cannot demote yourself.');
  abort_if($r->user()->id===$user->id && $r->has('is_active') && !$r->boolean('is_active'),422,'You cannot deactivate your own account.');
  $data=$r->validate(['name'=>'sometimes|required|string|max:120','email'=>['sometimes','required','email','max:255',Rule::unique('users','email')->ignore($user->id),Rule::unique('users','pending_email')->ignore($user->id)],
   'course'=>'sometimes|nullable|string|max:140',
   'faculty_position'=>'sometimes|nullable|string|max:120',
   'department_id'=>'sometimes|nullable|exists:departments,id',
   'role'=>'sometimes|in:student,faculty,officer,president,admin',
   'is_active'=>'sometimes|boolean',
   'officer_permissions'=>'sometimes|array','officer_permissions.*'=>Rule::in(
    $r->input('role',$user->role)==='faculty'?['events','announcements']:['events','announcements','members']
   )]);
  $previousRole=$user->role;
  $previousDepartmentId=$user->department_id;
  $previousPermissions=$user->officer_permissions??[];
  $role=$data['role']??$user->role;
  $dept=array_key_exists('department_id',$data)?$data['department_id']:$user->department_id;
  if(in_array($role,['officer','president','faculty']) && !$dept)return response()->json(['message'=>'Faculty, officers, and presidents must belong to a department.'],422);
  if(!in_array($role,['officer','faculty'],true))$data['officer_permissions']=[];
  if($role!=='faculty')$data['faculty_position']=null;
  return DB::transaction(function()use($r,$user,$data,$previousRole,$previousDepartmentId,$previousPermissions){
   $user->update($data);
   if(array_key_exists('is_active',$data) && !$data['is_active'])$user->tokens()->delete();
   $user=$user->fresh()->load(['department']);
   $key='account:'.$user->id.':updated:'.\Illuminate\Support\Str::uuid();

   if($previousRole!==$user->role && in_array($user->role,['president','officer','faculty'],true)){
    $roleName=match($user->role){'president'=>'Department President','faculty'=>'Faculty',default=>'Department Officer'};
    Hub::notify($user,'account_role','Role assignment updated',
     'You have been assigned as '.$roleName.' for '.$user->department?->name.'.',
     '/organizer',$key.':role');
   }
   if($previousDepartmentId!==$user->department_id){
    $departmentName=$user->department?->name??'no department';
    Hub::notify($user,'membership','Department assignment changed',
     'Your department assignment is now '.$departmentName.'.','/profile',$key.':department');
   }
   if($user->role==='officer' && $previousPermissions!==($user->officer_permissions??[])){
    Hub::notify($user,'account_role','Officer permissions changed',
     'Your department officer permissions were updated.','/organizer',$key.':permissions');
   }

   Hub::log($r->user(),'user.updated','user',$user->id,['role'=>$user->role]);
   return $user;
  });
 }
 public function destroyUser(Request $r,User $user){
  abort_if($r->user()->id===$user->id,422,'You cannot delete your own admin account.');
  $id=$user->id;$user->delete();Hub::log($r->user(),'user.deleted','user',$id);return ['message'=>'Account deleted.'];
 }
 public function storeDepartment(Request $r){
  $data=$r->validate(['name'=>'required|string|max:150','code'=>'required|alpha_dash|max:20|unique:departments,code','description'=>'nullable|string|max:3000','email'=>'nullable|email|max:255']);
  $d=Department::create($data);Hub::log($r->user(),'department.created','department',$d->id);return response()->json($d,201);
 }
 public function updateDepartment(Request $r,Department $department){
  $data=$r->validate(['name'=>'sometimes|required|string|max:150','code'=>['sometimes','required','alpha_dash','max:20',Rule::unique('departments')->ignore($department->id)],
   'description'=>'nullable|string|max:3000','email'=>'nullable|email|max:255']);
  $department->update($data);Hub::log($r->user(),'department.updated','department',$department->id);return $department->fresh();
 }
 public function destroyDepartment(Request $r,Department $department){
  abort_if($department->users()->exists() || $department->events()->exists(),422,'Move users and events out of this department before deleting it.');
  $id=$department->id;$department->delete();Hub::log($r->user(),'department.deleted','department',$id);return ['message'=>'Department deleted.'];
 }
 public function reports(Request $r){
  return DB::table('event_reports as p')->join('events as e','e.id','=','p.event_id')->join('users as u','u.id','=','p.user_id')
   ->select('p.*','e.title as event_title','u.name as reporter')->orderByDesc('p.created_at')->paginate(30);
 }
 public function resolveReport(Request $r,int $id){
  $data=$r->validate(['status'=>'required|in:under_review,resolved,dismissed','resolution'=>'nullable|string|max:3000']);
  return DB::transaction(function()use($r,$id,$data){
   $report=DB::table('event_reports as p')->join('events as e','e.id','=','p.event_id')
    ->where('p.id',$id)->lockForUpdate()
    ->select('p.id','p.user_id','p.event_id','p.status','e.title as event_title')->first();
   abort_unless($report,404);
   abort_unless(in_array($report->status,['pending','under_review'],true),422,'Only pending or under-review reports can be updated.');

   DB::table('event_reports')->where('id',$id)->update([
    'status'=>$data['status'],'resolution'=>$data['resolution']??null,'updated_at'=>now(),
   ]);
   $statusLabel=str_replace('_',' ',$data['status']);
   $statusLabel=ucfirst($statusLabel);
   Hub::notify(User::find($report->user_id),'event_report','Event report status updated',
    'Your report #'.$id.' for '.$report->event_title.' is now '.$statusLabel.'.',
    '/notifications','event-report:'.$id.':status:'.$data['status']);
   Hub::log($r->user(),'report.'.$data['status'],'event_report',$id);

   return ['message'=>'Report updated.','status'=>$data['status']];
  });
 }
 public function audits(){return DB::table('audit_logs as a')->leftJoin('users as u','u.id','=','a.actor_id')->select('a.*','u.name as actor_name')->orderByDesc('a.created_at')->paginate(40);}
 public function settings(){return DB::table('system_settings')->pluck('value','key');}
 public function saveSettings(Request $r){
  $data=$r->validate(['campus_name'=>'sometimes|required|string|max:150','contact_email'=>'sometimes|nullable|email|max:255','registration_open'=>'sometimes|boolean']);
  foreach($data as $key=>$value)DB::table('system_settings')->updateOrInsert(['key'=>$key],['value'=>is_bool($value)?($value?'1':'0'):$value,'updated_at'=>now(),'created_at'=>now()]);
  Hub::log($r->user(),'settings.updated');return $this->settings();
 }
 public function exportReporting(){
  $events=Event::with('department:id,name,code')->withCount(['rsvps as attendee_count'=>fn($q)=>$q->where('status','active')])->orderBy('starts_at')->get();
  return response()->streamDownload(function()use($events){
   $out=fopen('php://output','w');fputcsv($out,['Event ID','Event title','Department','Start','Status','RSVPs','Capacity','Views']);
   foreach($events as $e){$safe=fn($s)=>preg_match('/^[=+\-@\t\r]/u',(string)$s)?"'".$s:$s;
    fputcsv($out,[$e->id,$safe($e->title),$safe($e->department?->name??''),$e->starts_at?->format('Y-m-d H:i:s'),$e->status,$e->attendee_count,$e->seat_limit,$e->views]);
   }fclose($out);
  },'spc-campus-report.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
 }
 public function reporting(){
  return ['events_by_department'=>Department::withCount('events')->get(['id','name','code']),
   'top_events'=>Event::with('department:id,name')->withCount(['rsvps as attendee_count'=>fn($q)=>$q->where('status','active')])->orderByDesc('views')->limit(15)->get(),
   'memberships'=>Membership::selectRaw('status,count(*) as total')->groupBy('status')->get()];
 }
}
