<?php
namespace App\Http\Controllers;
use App\Models\{Announcement,Department,Event,Membership,User};
use App\Services\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Password};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
class AdminController extends Controller {
 private const PRESENCE_TIMEOUT_MINUTES = 2;
 private const HEARTBEAT_INTERVAL_SECONDS = 30;

 public function updateAvailability(Request $r){
  $data=$r->validate(['status'=>'required|in:online,busy']);
  $user=$r->user();
  abort_unless($user->role==='admin',403);
  $user->forceFill([
   'availability_preference'=>$data['status'],
   'last_active_at'=>now(),
  ])->save();
  return ['preference'=>$data['status'],'status'=>$data['status']==='online'?'Online':'Busy'];
 }
 public function heartbeat(Request $r){
  $user=$r->user();
  abort_unless($user->role==='admin',403);
  $cutoff=now()->subSeconds(self::HEARTBEAT_INTERVAL_SECONDS);
  User::whereKey($user->id)
   ->where(fn($q)=>$q->whereNull('last_active_at')->orWhere('last_active_at','<=',$cutoff))
   ->update(['last_active_at'=>now()]);
  return response()->noContent();
 }
 public function adminAvailability(){
  $activeAdmins=User::where('role','admin')->where('is_active',true)
   ->where('last_active_at','>=',now()->subMinutes(self::PRESENCE_TIMEOUT_MINUTES))
   ->whereHas('tokens',fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()));
  if((clone $activeAdmins)->where('availability_preference','online')->exists()){
   return ['status'=>'Online'];
  }
  if($activeAdmins->exists()){
   return ['status'=>'Busy'];
  }
  return ['status'=>'Offline'];
 }

 public function stats(){
  return ['students'=>User::where('role','student')->count(),'officers'=>User::whereIn('role',['officer','president'])->count(),
   'departments'=>Department::count(),'events'=>Event::count(),'upcoming'=>Event::where('status','published')->where('starts_at','>=',now())->count(),
   'rsvps'=>DB::table('rsvps')->where('status','active')->count(),'reports'=>DB::table('event_reports')->where('status','pending')->count(),
   'announcements'=>Announcement::count()];
 }
 public function allEvents(){return Event::with(['department:id,name,code'])->withCount(['rsvps as attendee_count'=>fn($q)=>$q->where('status','active')])->latest()->get();}
 public function destroyEvent(Request $r,Event $event){
  $event->delete();
  Hub::log($r->user(),'event.deleted','event',$event->id);
  return ['message'=>'Event removed from listings. RSVP and attendance history has been preserved.'];
 }
 public function allAnnouncements(){return Announcement::with(['department:id,name,code'])->latest()->get();}
 public function users(Request $r){
  $filters=$r->validate([
   'role'=>'nullable|in:student,faculty,leadership,admin',
   'year_level'=>['nullable',Rule::in(['1st Year','2nd Year','3rd Year','4th Year'])],
   'search'=>'nullable|string|max:200',
  ]);
  $q=User::with(['department:id,name,code']);
  if(($filters['role']??null)==='leadership')$q->whereIn('role',['president','officer']);
  elseif(!empty($filters['role']))$q->where('role',$filters['role']);
  if(!empty($filters['year_level']))$q->where('year_level',$filters['year_level']);
  if(!empty($filters['search']))$q->where(fn($s)=>$s->where('name','like','%'.$filters['search'].'%')->orWhere('email','like','%'.$filters['search'].'%'));
  $primaryAdminId=User::where('role','admin')->orderBy('id')->value('id');
  $activeAdminCount=User::where('role','admin')->where('is_active',true)->count();
  $users=$q->orderBy('name')->paginate(30);
  $users->getCollection()->transform(function(User $user)use($primaryAdminId,$activeAdminCount){
   $isPrimaryAdmin=$primaryAdminId!==null&&(int)$user->id===(int)$primaryAdminId;
   $user->setAttribute('is_primary_admin',$user->role==='admin'&&$isPrimaryAdmin);
   $user->setAttribute('is_protected_admin',$user->role==='admin'
    &&($isPrimaryAdmin||($user->is_active&&$activeAdminCount===1)));
   return $user;
  });
  return $users;
 }
 public function courses(Request $r){
  $data=$r->validate(['department_id'=>'required|exists:departments,id']);
  return User::where('department_id',$data['department_id'])
   ->whereNotNull('course')->whereRaw("TRIM(course) <> ''")
   ->distinct()->orderBy('course')->pluck('course');
 }
 public function storeUser(Request $r){
  $role=$r->input('role');
  $permissions=in_array($role,['faculty','officer'],true)
   ?($role==='faculty'?['events','announcements','attendance']:['events','announcements','attendance','members'])
   :[];
  $rules=[
   'name'=>'required|string|max:120',
   'email'=>['required','email','max:255',Rule::unique('users','email'),Rule::unique('users','pending_email')],
   'role'=>'required|in:student,faculty,president,officer',
   'password_setup'=>'required|in:temporary_password,email_invitation',
   'password'=>['exclude_unless:password_setup,temporary_password','required','confirmed',PasswordRule::min(8)],
   'department_id'=>'required|exists:departments,id',
   'course'=>'nullable|string|max:140|required_if:role,student',
   'year_level'=>['nullable','required_if:role,student',Rule::in(['1st Year','2nd Year','3rd Year','4th Year'])],
   'faculty_position'=>'nullable|string|max:120',
  ];
  if($permissions){
   $rules['officer_permissions']='sometimes|array';
   $rules['officer_permissions.*']=Rule::in($permissions);
  }else{
   $rules['officer_permissions']='prohibited';
  }
  $data=$r->validate($rules);
  $department=Department::findOrFail($data['department_id']);
  return DB::transaction(function()use($r,$data,$department){
   $temporaryPassword=$data['password_setup']==='temporary_password';
   $user=User::create([
    'name'=>$data['name'],
    'email'=>strtolower($data['email']),
    'password'=>$temporaryPassword?$data['password']:Str::random(64),
    'role'=>$data['role'],
    'department_id'=>$department->id,
    'course'=>$data['course']??null,
    'year_level'=>$data['role']==='student'?($data['year_level']??null):null,
    'faculty_position'=>$data['role']==='faculty'?($data['faculty_position']??null):null,
    'officer_permissions'=>in_array($data['role'],['faculty','officer'],true)?array_values(array_unique($data['officer_permissions']??[])):[],
    'is_active'=>$temporaryPassword,
    'must_change_password'=>$temporaryPassword,
    'activation_pending'=>!$temporaryPassword,
    'preferences'=>['notifications_enabled'=>true,'reminders_enabled'=>true,'announcement_alerts_enabled'=>true],
   ]);
   if($temporaryPassword){
    $message='User account created with a temporary password. The user must change it at first login.';
   }else{
    if(in_array(config('mail.default'),['log','array'],true)){
     throw ValidationException::withMessages([
      'password_setup'=>'Email delivery is not configured. Configure a working mailer or choose Set Temporary Password.',
     ]);
    }
    try{
     $status=Password::sendResetLink(['email'=>$user->email]);
    }catch(\Symfony\Component\Mailer\Exception\TransportExceptionInterface $exception){
     throw ValidationException::withMessages([
      'password_setup'=>'The invitation email could not be delivered. Check the mail configuration and try again.',
     ]);
    }
    if($status!==Password::RESET_LINK_SENT){
     throw ValidationException::withMessages(['email'=>__($status)]);
    }
    $message='Invitation email sent. The account remains inactive until the recipient sets a password using the expiring link.';
   }
   Hub::log($r->user(),'user.created','user',$user->id,['role'=>$user->role,'department_id'=>$department->id]);
   return response()->json([
    'message'=>$message,
    'user'=>$user->load(['department']),
   ],201);
  });
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
   'year_level'=>['sometimes','nullable',Rule::in(['1st Year','2nd Year','3rd Year','4th Year'])],
   'faculty_position'=>'sometimes|nullable|string|max:120',
   'department_id'=>'sometimes|nullable|exists:departments,id',
   'role'=>'sometimes|in:student,faculty,officer,president,admin',
   'is_active'=>'sometimes|boolean',
   'officer_permissions'=>'sometimes|array','officer_permissions.*'=>Rule::in(
    $r->input('role',$user->role)==='faculty'?['events','announcements','attendance']:['events','announcements','attendance','members']
   )]);
  $role=$data['role']??$user->role;
  if($role!=='student')$data['year_level']=null;
  $dept=array_key_exists('department_id',$data)?$data['department_id']:$user->department_id;
  if(in_array($role,['officer','president','faculty']) && !$dept)return response()->json(['message'=>'Faculty, officers, and presidents must belong to a department.'],422);
  if(!in_array($role,['officer','faculty'],true))$data['officer_permissions']=[];
  if($role!=='faculty')$data['faculty_position']=null;
  return DB::transaction(function()use($r,$user,$data){
   $adminAccounts=User::where('role','admin')->orderBy('id')->lockForUpdate()->get(['id','is_active']);
   $user=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
   $previousRole=$user->role;
   $previousDepartmentId=$user->department_id;
   $previousPermissions=$user->officer_permissions??[];
   $role=$data['role']??$user->role;
   $willBeActive=array_key_exists('is_active',$data)?(bool)$data['is_active']:(bool)$user->is_active;
   $removingAdmin=$user->role==='admin'
    &&($role!=='admin'||($user->is_active&&array_key_exists('is_active',$data)&&!$willBeActive));
   if($removingAdmin){
    if($adminAccounts->first()?->id===$user->id)abort(422,'The Primary Admin account cannot be deleted, deactivated, or demoted.');
    if($user->is_active&&$adminAccounts->where('is_active',true)->count()<=1){
     abort(422,'The last active Admin account cannot be deleted, deactivated, or demoted.');
    }
   }
   if($r->user()->id===$user->id&&$role!=='admin')abort(422,'You cannot demote yourself.');
   if($r->user()->id===$user->id&&$user->is_active&&!$willBeActive)abort(422,'You cannot deactivate your own account.');
   if($role!=='student')$data['year_level']=null;
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
  return DB::transaction(function()use($r,$user){
   $adminAccounts=User::where('role','admin')->orderBy('id')->lockForUpdate()->get(['id','is_active']);
   $target=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
   abort_if($r->user()->id===$target->id,422,'You cannot delete your own Admin account.');
   if($target->role==='admin'){
    if($adminAccounts->first()?->id===$target->id)abort(422,'The Primary Admin account cannot be deleted, deactivated, or demoted.');
    if($target->is_active&&$adminAccounts->where('is_active',true)->count()<=1){
     abort(422,'The last active Admin account cannot be deleted, deactivated, or demoted.');
    }
   }
   $id=$target->id;
   $target->delete();
   Hub::log($r->user(),'user.deleted','user',$id);
   return ['message'=>'Account deleted.'];
  });
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
