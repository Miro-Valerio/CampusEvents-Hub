<?php
namespace App\Http\Controllers;
use App\Models\{Event,EventCheckIn,Rsvp,User};
use App\Services\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
class OrganizerController extends Controller {
 private function department(Request $r,?int $id=null):int {
  if($r->user()->role==='admin')return (int)($id??$r->input('department_id')??abort(422,'Select a department.'));
  abort_unless(in_array($r->user()->role,['president','officer','faculty'],true),403,'This action requires department permissions.');
  return (int)$r->user()->department_id;
 }
 public function officers(Request $r){
  $user=$r->user();$dept=$this->department($r);
  abort_unless($user->role==='admin'||($user->role==='president' && (int)$user->department_id===$dept),403);
  return User::where('department_id',$dept)->where('role','officer')->select('id','name','email','course','department_id','role','officer_permissions')->orderBy('name')->get();
 }
 public function permissions(Request $r,User $officer){
  $user=$r->user();
  abort_unless($officer->role==='officer' && ($user->role==='admin'||($user->role==='president' && (int)$user->department_id===(int)$officer->department_id)),403);
  $data=$r->validate(['officer_permissions'=>'required|array','officer_permissions.*'=>'in:events,announcements,attendance,members']);
  return DB::transaction(function()use($user,$officer,$data){
   $previous=$officer->officer_permissions??[];
   $officer->update(['officer_permissions'=>array_values(array_unique($data['officer_permissions']))]);
   if($previous!==($officer->officer_permissions??[])){
    Hub::notify($officer,'account_role','Officer permissions changed',
     'Your department officer permissions were updated.','/organizer',
     'officer:'.$officer->id.':permissions:'.Str::uuid());
   }
   Hub::log($user,'officer.permissions_updated','user',$officer->id);
   return ['message'=>'Officer permissions updated.','user'=>$officer->fresh()];
  });
 }
 public function overview(Request $r){
  $user=$r->user();
  $dept=$this->department($r);
  abort_unless($dept && in_array($user->role,['admin','president','officer','faculty']),403);
  if($user->role==='faculty'){
   abort_unless($user->canDepartment('events',$dept)||$user->canDepartment('announcements',$dept)||$user->canDepartment('attendance',$dept),403);
  }
  return ['events'=>Event::where('department_id',$dept)->count(),
   'rsvps'=>Rsvp::whereHas('event',fn($q)=>$q->where('department_id',$dept))->where('status','active')->count(),
   'members'=>DB::table('memberships')->where('department_id',$dept)->where('status','approved')->count(),
   'pending'=>DB::table('memberships')->where('department_id',$dept)->where('status','pending')->count()];
 }
 public function index(Request $r){
  $dept=$this->department($r);Hub::canManage($r->user(),'events',$dept);
  $events=Event::where('department_id',$dept)
   ->with(['department:id,name,code'])
   ->withCount(['rsvps as attendee_count'=>fn($q)=>$q->where('status','active')])
   ->orderByDesc('created_at')->get();
  $rsvps=Rsvp::whereIn('event_id',$events->pluck('id'))->where('status','active')
   ->with(['event','checkin'])->get()->groupBy('event_id');
  foreach($events as $event){
   $states=($rsvps->get($event->id)??collect())->groupBy('attendance_status');
   $attendanceClosesAt=$event->requires_check_out?$event->check_out_closes_at:$event->ends_at;
   $event->setAttribute('attended_count',$states->get('Completed',collect())->count()+$states->get('Verified',collect())->count());
   $event->setAttribute('pending_checkin_count',$states->get('Pending Verification',collect())->count());
   $event->setAttribute('verified_checkin_count',$states->get('Verified',collect())->count());
   $event->setAttribute('absent_count',$states->get('Absent',collect())->count());
   $event->setAttribute('attendance_window_closed',$event->status!=='cancelled'&&$attendanceClosesAt&&now()->greaterThan($attendanceClosesAt));
  }
  return $events;
 }
 public function attendanceEvents(Request $r){
  $dept=$this->department($r,$r->integer('department_id')?:null);
  Hub::canManage($r->user(),'attendance',$dept);
  return Event::where('department_id',$dept)
   ->select('id','title','status','starts_at','ends_at','requires_check_out','check_out_opens_at','check_out_closes_at')
   ->orderByDesc('starts_at')->get();
 }
 private function rules(bool $updating=false):array {
  $prefix=$updating?'sometimes|required':'required';
  return ['title'=>$prefix.'|string|max:200','description'=>$prefix.'|string|max:10000',
   'venue'=>$prefix.'|string|max:200','category'=>$prefix.'|string|max:60',
   'starts_at'=>$prefix.'|date','ends_at'=>$prefix.'|date|after:starts_at',
   'seat_limit'=>$prefix.'|integer|min:1|max:10000','rules'=>'nullable|string|max:5000',
   'poster'=>'nullable|image|max:5120','department_id'=>'nullable|exists:departments,id',
   'requires_check_out'=>'sometimes|boolean','check_out_opens_at'=>'nullable|date',
   'check_out_closes_at'=>'nullable|date'];
 }
 private function validateCheckOutWindow(array &$data,?Event $event=null):void {
  $required=(bool)($data['requires_check_out']??$event?->requires_check_out??false);
  $data['requires_check_out']=$required;
  if(!$required){
   $data['check_out_opens_at']=null;
   $data['check_out_closes_at']=null;
   return;
  }
  $starts=\Carbon\Carbon::parse($data['starts_at']??$event->starts_at);
  $ends=\Carbon\Carbon::parse($data['ends_at']??$event->ends_at);
  $opensAt=$data['check_out_opens_at']??$event?->check_out_opens_at;
  $closesAt=$data['check_out_closes_at']??$event?->check_out_closes_at;
  $errors=[];
  if(!$opensAt)$errors['check_out_opens_at'][]='Select when check-out opens.';
  if(!$closesAt)$errors['check_out_closes_at'][]='Select when check-out closes.';
  if($opensAt&&$closesAt){
   $opensAt=\Carbon\Carbon::parse($opensAt);
   $closesAt=\Carbon\Carbon::parse($closesAt);
   if($opensAt->lessThan($starts)||$opensAt->greaterThan($ends))$errors['check_out_opens_at'][]='Check-out must open during the event.';
   if($closesAt->lessThan($ends)||$closesAt->greaterThan($ends->copy()->addHours(2))||!$closesAt->greaterThan($opensAt)){
    $errors['check_out_closes_at'][]='Check-out must close after the event and no later than two hours afterward.';
   }
   $data['check_out_opens_at']=$opensAt;
   $data['check_out_closes_at']=$closesAt;
  }
  if($errors)throw ValidationException::withMessages($errors);
 }
 public function store(Request $r){
  $data=$r->validate($this->rules());
  $this->validateCheckOutWindow($data);
  $user=$r->user();
  $dept=match($user->role){
   'admin'=>(int)($data['department_id']??0)?:null,
   default=>(int)$user->department_id,
  };
  if($user->role!=='admin'){
   Hub::canManage($user,'events',(int)$dept);
  }
  abort_unless(\Carbon\Carbon::parse($data['starts_at'])->isFuture(),422,'Event must start in the future.');
  $data['poster_path']=$r->hasFile('poster')?$r->file('poster')->store('posters','public'):null;
  unset($data['poster'],$data['department_id']);
  $event=DB::transaction(function()use($r,$data,$dept){
   $event=Event::create($data+[
    'department_id'=>$dept,'created_by'=>$r->user()->id,'status'=>'published',
   ]);
   $key='event:'.$event->id.':published';
   $message=$event->title.' is scheduled for '.$event->starts_at->format('M d, Y g:i A').' at '.$event->venue.'.';
   if($dept){
    Hub::notifyDepartmentAudience($dept,null,'event','New department event',$message,
     '/events/'.$event->id,$key.':students',$r->user()->id);
    Hub::notifyAdmins('event','New department event published',$message,
     '/events/'.$event->id,$key.':admins',$r->user()->id);
    Hub::notifyDepartmentManagers($dept,'events','event','New department event published',$message,
     '/events/'.$event->id,$key.':managers',$r->user()->id);
   }
   Hub::log($r->user(),'event.created','event',$event->id);
   return $event->load(['department']);
  });
  return response()->json($event,201);
 }
 public function update(Request $r,Event $event){
  $user=$r->user();
  $this->authorizeEvent($user,$event);
  abort_if($event->status==='cancelled',422,'Cancelled events cannot be edited.');
  $data=$r->validate($this->rules(true));
  $this->validateCheckOutWindow($data,$event);
  $oldStart=$event->starts_at->toIso8601String();$oldEnd=$event->ends_at->toIso8601String();$oldVenue=$event->venue;
  $oldState=[
   $event->title,$event->description,$event->venue,$event->starts_at->toIso8601String(),
   $event->ends_at->toIso8601String(),   $event->seat_limit,
  ];
  if(isset($data['seat_limit']) && $data['seat_limit']<$event->rsvps()->where('status','active')->count())abort(422,'Seat limit cannot be lower than current RSVPs.');
  $oldPosterPath=$event->poster_path;
  if($r->hasFile('poster')){
   $data['poster_path']=$r->file('poster')->store('posters','public');
  }
  unset($data['poster'],$data['department_id']);
  $updated=DB::transaction(function()use($r,$event,$data,$oldStart,$oldEnd,$oldVenue,$oldState,$user){
   $event->update($data);
   $event=$event->fresh();
   $changes=[];
   if($oldStart!==$event->starts_at->toIso8601String() || $oldEnd!==$event->ends_at->toIso8601String()){
    $changes[]='the schedule is now '.$event->starts_at->format('M d, Y g:i A');
    Rsvp::where('event_id',$event->id)->where('status','active')->update(['reminder_sent_at'=>null]);
   }
   if($oldVenue!==$event->venue)$changes[]='the venue is now '.$event->venue;
   $newState=[
    $event->title,$event->description,$event->venue,$event->starts_at->toIso8601String(),
    $event->ends_at->toIso8601String(),    $event->seat_limit,
   ];
   $changed=$oldState!==$newState;
   if($changed){
    if(!$changes){$changes[]='the event details or audience changed';}
    $ids=DB::table('bookmarks')->where('event_id',$event->id)->pluck('user_id')
     ->merge(Rsvp::where('event_id',$event->id)->where('status','active')->pluck('user_id'))->unique()->all();
    $key='event:'.$event->id.':updated:'.Str::uuid();
    User::whereIn('id',$ids)->orderBy('id')->chunkById(200,function($users)use($event,$changes,$key){
     foreach($users as $u)Hub::notify($u,'event_update','Event schedule or venue changed',
      $event->title.' was updated: '.implode('; ',$changes).'.','/events/'.$event->id,$key.':'.$u->id);
    });
    if($event->department_id){
     Hub::notifyDepartmentManagers($event->department_id,'events','event_update','Event details changed',
      $event->title.' was updated: '.implode('; ',$changes).'.','/events/'.$event->id,$key.':managers',$user->id);
    }
    if($event->created_by!==$user->id){
     Hub::notify(User::find($event->created_by),'event_update','Your event was updated',
      $event->title.' was updated: '.implode('; ',$changes).'.','/events/'.$event->id,$key.':organizer');
    }
   }
   Hub::log($user,'event.updated','event',$event->id);
   return $event->load(['department'])
    ->loadCount(['rsvps as attendee_count'=>fn($q)=>$q->where('status','active')]);
  });
  if($r->hasFile('poster') && $oldPosterPath)Storage::disk('public')->delete($oldPosterPath);
  return $updated;
 }
 public function cancel(Request $r,Event $event){
  $this->authorizeEvent($r->user(),$event);
  abort_if($event->status==='cancelled',422,'Already cancelled.');
  return DB::transaction(function()use($r,$event){
   $event->update(['status'=>'cancelled']);
   $ids=Rsvp::where('event_id',$event->id)->where('status','active')->pluck('user_id')
    ->merge(DB::table('bookmarks')->where('event_id',$event->id)->pluck('user_id'))->unique()->all();
   $key='event:'.$event->id.':cancelled:'.$event->updated_at?->format('YmdHis');
   User::whereIn('id',$ids)->orderBy('id')->chunkById(200,function($users)use($event,$key){
    foreach($users as $u)Hub::notify($u,'event_cancelled','Event cancelled',
     $event->title.' has been cancelled.','/events/'.$event->id,$key.':'.$u->id);
   });
   if($event->department_id){
    Hub::notifyDepartmentManagers($event->department_id,'events','event_cancelled','Department event cancelled',
     $event->title.' has been cancelled.','/events/'.$event->id,$key.':managers',$r->user()->id);
   }
   if($event->created_by!==$r->user()->id){
    Hub::notify(User::find($event->created_by),'event_cancelled','Your event was cancelled',
     $event->title.' was cancelled.','/events/'.$event->id,$key.':organizer');
   }
   Hub::log($r->user(),'event.cancelled','event',$event->id);
   return ['message'=>'Event cancelled and participants notified.'];
  });
 }
 public function destroyEvent(Request $r,Event $event){
  $this->authorizeEvent($r->user(),$event);
  $event->delete();
  Hub::log($r->user(),'event.deleted','event',$event->id);
  return ['message'=>'Event removed from listings. RSVP and attendance history has been preserved.'];
 }
 private function studentRsvps(Request $r,Event $event):array {
  $filters=$r->validate([
   'course'=>'nullable|string|max:140',
   'year_level'=>['nullable',Rule::in(['1st Year','2nd Year','3rd Year','4th Year'])],
  ]);
  $query=Rsvp::where('event_id',$event->id)->where('status','active')
   ->whereHas('user',fn($user)=>$user->where('role','student'));
  if(!empty($filters['course']))$query->whereHas('user',fn($user)=>$user->where('course',$filters['course']));
  if(!empty($filters['year_level']))$query->whereHas('user',fn($user)=>$user->where('year_level',$filters['year_level']));
  return [$query,$filters];
 }
 public function attendees(Request $r,Event $event){
  $this->authorizeAttendance($r->user(),$event);
  [$attendeeQuery,$filters]=$this->studentRsvps($r,$event);
  $now=now();
  $checkInOpen=$event->status==='published'&&$now->between($event->starts_at->copy()->subHours(2),$event->ends_at);
  $checkOutOpen=$event->status==='published'&&$event->requires_check_out&&$event->check_out_opens_at&&$event->check_out_closes_at
   &&$now->between($event->check_out_opens_at,$event->check_out_closes_at);
  $userFields=$r->user()->role==='faculty'?'id,name,course,year_level':'id,name,email,course,year_level';
  $attendees=$attendeeQuery->with(['event','user:'.$userFields,'checkin'])->orderBy('seat_no')->get();
  $verified=$attendees->filter(fn($rsvp)=>$rsvp->checkin
   &&$rsvp->checkin->verification_status==='verified'
   &&(!$event->requires_check_out||$rsvp->checkin->checked_out_at))->count();
  $confirmed=$attendees->count();
  $attendanceWindowClosed=$event->status!=='cancelled'
   &&($event->requires_check_out?$event->check_out_closes_at:$event->ends_at)
   &&now()->greaterThan($event->requires_check_out?$event->check_out_closes_at:$event->ends_at);
  return [
   'event'=>$event->load(['department']),
   'check_in_open'=>(bool)$checkInOpen,
   'check_out_open'=>(bool)$checkOutOpen,
   'courses'=>DB::table('rsvps as registrations')
    ->join('users as students','students.id','=','registrations.user_id')
    ->where('registrations.event_id',$event->id)->where('registrations.status','active')
    ->where('students.role','student')
    ->whereNotNull('students.course')->whereRaw("TRIM(students.course) <> ''")
    ->distinct()->orderBy('students.course')->pluck('students.course'),
   'filters'=>['course'=>$filters['course']??null,'year_level'=>$filters['year_level']??null],
   'summary'=>[
    'confirmed_rsvps'=>$confirmed,
    'verified_attendees'=>$verified,
    'pending_verification'=>$attendees->filter(fn($rsvp)=>$rsvp->checkin?->verification_status==='pending')->count(),
    'absent_students'=>$attendanceWindowClosed?$attendees->filter(fn($rsvp)=>$rsvp->attendance_status==='Absent')->count():0,
    'attendance_percentage'=>$confirmed?round(100*$verified/$confirmed,1):null,
    'attendance_window_closed'=>(bool)$attendanceWindowClosed,
   ],
   'attendees'=>$attendees,
  ];
 }
 public function attendanceLookup(Request $r,Event $event){
  $this->authorizeAttendance($r->user(),$event);
  $data=$r->validate([
   'code'=>['required','string','size:32','regex:/\A[A-Fa-f0-9]{32}\z/'],
   'mode'=>'required|in:check_in,check_out',
  ]);
  $rsvp=$this->attendanceRsvp($event,$data['code']);
  $this->validateAttendanceAction($event,$rsvp,$data['mode']);

  return [
   'event'=>['id'=>$event->id,'title'=>$event->title],
   'student'=>[
    'name'=>$rsvp->user->name,
    'course'=>$rsvp->user->course,
    'year_level'=>$rsvp->user->year_level?:'Not Set',
   ],
   'mode'=>$data['mode'],
   'attendance_status'=>$rsvp->attendance_status,
  ];
 }
 public function checkInAttendance(Request $r,Event $event){
  return $this->recordAttendance($r,$event,'check_in');
 }
 public function checkOutAttendance(Request $r,Event $event){
  return $this->recordAttendance($r,$event,'check_out');
 }
 private function recordAttendance(Request $r,Event $event,string $mode){
  $this->authorizeAttendance($r->user(),$event);
  abort_unless(in_array($mode,['check_in','check_out'],true),404);
  $data=$r->validate(['code'=>['required','string','size:32','regex:/\A[A-Fa-f0-9]{32}\z/']]);
  return DB::transaction(function()use($r,$event,$mode,$data){
   $event=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   $rsvp=$this->attendanceRsvp($event,$data['code'],true);
   abort_if($event->status==='cancelled',422,'This event is cancelled.');
   if($mode==='check_in'&&$rsvp->checkin&&$rsvp->checkin->verification_status==='verified'){
    return ['message'=>'Attendance was already recorded.','attendance_status'=>$rsvp->attendance_status];
   }
   if($mode==='check_out'&&$rsvp->checkin?->checked_out_at){
    return ['message'=>'Check-out was already recorded.','attendance_status'=>'Completed'];
   }
   $this->validateAttendanceAction($event,$rsvp,$mode);
   $checkin=$rsvp->checkin;
   if($mode==='check_in'){
    if($checkin){
     $checkin->update([
      'verification_status'=>'verified',
      'verification_at'=>now(),
      'verified_by'=>$r->user()->id,
      'reviewed_by'=>$r->user()->id,
     ]);
    }else{
     $checkin=EventCheckIn::create([
      'rsvp_id'=>$rsvp->id,
      'verification_status'=>'verified',
      'verification_at'=>now(),
      'verified_by'=>$r->user()->id,
      'reviewed_by'=>$r->user()->id,
      'checked_at'=>now(),
     ]);
    }
    Hub::notify($rsvp->user,'checkin','Attendance verified',
     'Your attendance for '.$event->title.' was verified by an organizer.',
     '/events/'.$event->id,'event-checkin:'.$checkin->id.':verified');
    return ['message'=>'Attendance confirmed.','attendance_status'=>'Verified'];
   }
   $checkin->update(['checked_out_at'=>now(),'checked_out_by'=>$r->user()->id]);
   Hub::notify($rsvp->user,'checkin','Attendance completed',
    'Your attendance for '.$event->title.' is complete.',
    '/events/'.$event->id,'event-checkout:'.$checkin->id.':completed');
   return ['message'=>'Check-out recorded. Attendance is complete.','attendance_status'=>'Completed'];
  });
 }
 private function attendanceRsvp(Event $event,string $code,bool $lock=false):Rsvp {
  $normalized=strtoupper(trim($code));
  $query=Rsvp::with(['user:id,name,course,year_level','checkin','event'])
   ->where('attendance_code_hash',hash('sha256',$normalized));
  if($lock)$query->lockForUpdate();
  $rsvp=$query->first();
  abort_unless($rsvp,422,'Invalid or expired attendance code.');
  abort_unless((int)$rsvp->event_id===(int)$event->id,422,'This attendance code belongs to a different event.');
  abort_unless($rsvp->status==='active',422,'This registration is cancelled.');
  return $rsvp;
 }
 private function validateAttendanceAction(Event $event,Rsvp $rsvp,string $mode):void {
  abort_if($event->status==='cancelled',422,'This event is cancelled.');
  abort_unless($event->status==='published',422,'Attendance is unavailable for this event.');
  $checkin=$rsvp->checkin;
  if($mode==='check_in'){
   abort_if(!$event->ends_at||now()->greaterThan($event->ends_at),422,'This event has ended; attendance codes have expired.');
   abort_unless(now()->between($event->starts_at->copy()->subHours(2),$event->ends_at),422,'Check-in is not open yet. It opens two hours before the event starts.');
   if($checkin){
    if($checkin->verification_status==='pending')return;
    abort_if($checkin->verification_status==='rejected',422,'This attendance code was already rejected.');
    abort(422,'This attendance code has already been used.');
   }
   return;
  }
  abort_unless($event->requires_check_out,422,'Check-out is not required for this event.');
  abort_unless($event->check_out_opens_at&&$event->check_out_closes_at
   &&now()->between($event->check_out_opens_at,$event->check_out_closes_at),422,'Check-out is not open for this event.');
  abort_unless($checkin&&$checkin->verification_status==='verified',422,'A verified check-in is required before check-out.');
  abort_if($checkin->checked_out_at,422,'This attendance code has already been used for check-out.');
 }
 public function verify(Request $r,Event $event,Rsvp $rsvp){
  $this->authorizeAttendance($r->user(),$event);
  return DB::transaction(function()use($r,$event,$rsvp){
   $event=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   $rsvp=Rsvp::whereKey($rsvp->id)->lockForUpdate()->firstOrFail();
   abort_unless($rsvp->event_id===$event->id && $rsvp->status==='active',422,'Invalid attendee.');
   $checkin=EventCheckIn::where('rsvp_id',$rsvp->id)->lockForUpdate()->firstOrFail();
   abort_unless($checkin->verification_status==='pending',422,'Only pending check-ins can be approved.');
   $checkin->update([
    'verification_status'=>'verified',
    'verification_at'=>now(),
    'verified_by'=>$r->user()->id,
    'reviewed_by'=>$r->user()->id,
   ]);
   Hub::notify($rsvp->user,'checkin','Attendance approved',
    'Your check-in for '.$event->title.' was approved. '.($event->requires_check_out?'Check out when the event ends to complete your attendance.':'Your attendance is complete.'),
    '/events/'.$event->id,'event-checkin:'.$checkin->id.':verified');
   return ['message'=>'Check-in approved.','attendance_status'=>$event->requires_check_out?'Verified':'Completed'];
  });
 }
 public function rejectCheckin(Request $r,Event $event,Rsvp $rsvp){
  $this->authorizeAttendance($r->user(),$event);
  return DB::transaction(function()use($r,$event,$rsvp){
   Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   $rsvp=Rsvp::whereKey($rsvp->id)->lockForUpdate()->firstOrFail();
   abort_unless($rsvp->event_id===$event->id&&$rsvp->status==='active',422,'Invalid attendee.');
   $checkin=EventCheckIn::where('rsvp_id',$rsvp->id)->lockForUpdate()->firstOrFail();
   abort_unless($checkin->verification_status==='pending',422,'Only pending check-ins can be rejected.');
   $checkin->update([
    'verification_status'=>'rejected',
    'verification_at'=>now(),
    'reviewed_by'=>$r->user()->id,
   ]);
   Hub::notify($rsvp->user,'checkin','Attendance rejected',
    'Your check-in for '.$event->title.' was rejected. Contact the department event organizers if you believe this is incorrect.',
    '/events/'.$event->id,'event-checkin:'.$checkin->id.':rejected');
   return ['message'=>'Check-in rejected. The student was notified.','attendance_status'=>'Rejected'];
  });
 }
 public function export(Request $r,Event $event){
  $this->authorizeAttendance($r->user(),$event);
  [$query]=$this->studentRsvps($r,$event);
  $userFields=$r->user()->role==='faculty'?'id,name,course,year_level':'id,name,email,course,year_level';
  $rows=$query->with(['event','user:'.$userFields,'checkin'])->orderBy('seat_no')->get();
  $facultyView=$r->user()->role==='faculty';
  return response()->streamDownload(function()use($rows,$facultyView){
   $out=fopen('php://output','w');fputcsv($out,$facultyView
    ?['Seat','Student','Course','Year Level','RSVP Status','Attendance Status','Checked In','Checked Out']
    :['Seat','Student','Email','Course','Year Level','RSVP Status','Attendance Status','Checked In','Checked Out']);
   foreach($rows as $row){
    $safe=fn($x)=>preg_match('/^[=+\-@\t\r]/u',(string)$x)?"'".$x:$x;
    $values=[$row->seat_no,$safe($row->user->name)];
    if(!$facultyView)$values[]=$safe($row->user->email);
    $values=array_merge($values,[$safe($row->user->course),$safe($row->user->year_level),$row->status,
     $row->attendance_status,$row->checkin?->checked_at?->format('Y-m-d H:i:s')??'',$row->checkin?->checked_out_at?->format('Y-m-d H:i:s')??'']);
    fputcsv($out,$values);
   }fclose($out);
  },'event-'.$event->id.'-rsvps.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
 }
  private function authorizeEvent(User $user,Event $event):void {
   if($user->role==='admin'){return;}
   Hub::canManage($user,'events',(int)$event->department_id);
  }
  private function authorizeAttendance(User $user,Event $event):void {
   if($user->role==='admin'){return;}
   Hub::canManage($user,'attendance',(int)$event->department_id);
  }
}
