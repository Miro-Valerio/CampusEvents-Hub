<?php
namespace App\Http\Controllers;
use App\Models\{Event,Rsvp,EventCheckIn,User};
use App\Services\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class StudentController extends Controller {
 public function activity(Request $r){
  $user=$r->user();
  $rsvps=Rsvp::where('user_id',$user->id)->with(['event.department','checkin'])->latest()->get();
  foreach($rsvps as $rsvp){
   $event=$rsvp->event;
   $status=$rsvp->attendance_status;
   $checkOutStillOpen=$event?->requires_check_out&&$event->check_out_closes_at&&$event->check_out_closes_at->isFuture();
   $canPresentCode=$rsvp->status==='active'&&$event?->status==='published'
    &&!in_array($status,['Completed','Absent','Rejected'],true)
    &&($event->ends_at?->isFuture()||$checkOutStillOpen);
   if($canPresentCode){
    $rsvp->attendance_code=$this->ensureAttendanceCode($rsvp);
   }
  }
  $bookmarks=Event::with('department')->whereIn('id',DB::table('bookmarks')->where('user_id',$user->id)->pluck('event_id'))->get();
  $calendar=Event::with('department')->whereIn('id',DB::table('calendar_entries')->where('user_id',$user->id)->pluck('event_id'))->get();
  return ['rsvps'=>$rsvps,'bookmarks'=>$bookmarks,'calendar'=>$calendar];
 }
 private function ensureAttendanceCode(Rsvp $rsvp):string {
  if($rsvp->attendance_token){
   if(!$rsvp->attendance_code_hash){
    $rsvp->forceFill(['attendance_code_hash'=>hash('sha256',$rsvp->attendance_token)])->save();
   }
   return $rsvp->attendance_token;
  }
  $code=strtoupper(bin2hex(random_bytes(16)));
  $rsvp->forceFill([
   'attendance_token'=>$code,
   'attendance_code_hash'=>hash('sha256',$code),
  ])->save();
  return $code;
 }
 public function rsvp(Request $r,Event $event){
  abort_unless(in_array($r->user()->role,['student','faculty'],true),403,'Only students and Faculty can RSVP.');
  abort_unless($event->status==='published',404);
  return DB::transaction(function()use($r,$event){
   $event=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   abort_unless($event->status==='published' && $event->starts_at->isFuture() && $event->ends_at->isFuture(),422,'This event is ended or not open for RSVP.');
   $existing=Rsvp::where('event_id',$event->id)->where('user_id',$r->user()->id)->first();
   if($existing?->status==='active'){
    $existing->attendance_code=$this->ensureAttendanceCode($existing);
    return response()->json(['message'=>'You already RSVP’d for this event.','rsvp'=>$existing],422);
   }
   $taken=Rsvp::where('event_id',$event->id)->where('status','active')->pluck('seat_no')->all();
   if(count($taken)>=$event->seat_limit)return response()->json(['message'=>'This event is full.'],422);
   $set=array_fill_keys($taken,true);$seat=1;
   while(isset($set[$seat]) && $seat<=$event->seat_limit)$seat++;
   $rsvp=Rsvp::updateOrCreate(['event_id'=>$event->id,'user_id'=>$r->user()->id],['status'=>'active','seat_no'=>$seat,'reminder_sent_at'=>null]);
   $rsvp->attendance_code=$this->ensureAttendanceCode($rsvp);
   $key='rsvp:'.$rsvp->id.':confirmed:'.Str::uuid();
   Hub::notify($r->user(),'rsvp','RSVP confirmed','Your seat #'.$seat.' is reserved for '.$event->title.'.','/events/'.$event->id,$key.':student');
   if($event->department_id){
    Hub::notifyDepartmentManagers($event->department_id,'events','rsvp','New event RSVP',
     $r->user()->name.' reserved seat #'.$seat.' for '.$event->title.'.',
     '/organizer?tab=attendees&department_id='.$event->department_id,$key.':managers',$r->user()->id);
   }elseif($event->created_by!==$r->user()->id){
    Hub::notify(User::find($event->created_by),'rsvp','New event RSVP',
     $r->user()->name.' reserved seat #'.$seat.' for '.$event->title.'.',
     '/organizer?tab=attendees',$key.':organizer');
   }
   return response()->json(['message'=>'RSVP confirmed. Seat #'.$seat.'.','rsvp'=>$rsvp],201);
  });
 }
 public function cancel(Request $r,Event $event){
  return DB::transaction(function()use($r,$event){
   $row=Rsvp::where('event_id',$event->id)->where('user_id',$r->user()->id)->where('status','active')->lockForUpdate()->firstOrFail();
   abort_if(EventCheckIn::where('rsvp_id',$row->id)->exists(),422,'You cannot cancel after checking in.');
   abort_if($event->starts_at->isPast(),422,'This event already started.');
   $row->update(['status'=>'cancelled','seat_no'=>null,'reminder_sent_at'=>null,'attendance_token'=>null,'attendance_code_hash'=>null]);
   $key='rsvp:'.$row->id.':cancelled:'.Str::uuid();
   Hub::notify($r->user(),'rsvp_cancelled','RSVP cancelled','Your reservation for '.$event->title.' was cancelled.','/events/'.$event->id,$key.':student');
   if($event->department_id){
    Hub::notifyDepartmentManagers($event->department_id,'events','rsvp_cancelled','RSVP cancelled',
     $r->user()->name.' cancelled a reservation for '.$event->title.'.',
     '/organizer?tab=attendees&department_id='.$event->department_id,$key.':managers',$r->user()->id);
   }elseif($event->created_by!==$r->user()->id){
    Hub::notify(User::find($event->created_by),'rsvp_cancelled','RSVP cancelled',
     $r->user()->name.' cancelled a reservation for '.$event->title.'.',
     '/organizer?tab=attendees',$key.':organizer');
   }
   return ['message'=>'RSVP cancelled; your seat is now available.'];
  });
 }
 public function toggleBookmark(Request $r,Event $event){abort_unless($event->status==='published',404);return $this->togglePivot($r,'bookmarks',$event);}
 public function toggleCalendar(Request $r,Event $event){abort_unless($event->status==='published',404);return $this->togglePivot($r,'calendar_entries',$event);}
 private function togglePivot(Request $r,string $table,Event $event){
  $key=['user_id'=>$r->user()->id,'event_id'=>$event->id];
  $exists=DB::table($table)->where($key)->exists();
  if($exists)DB::table($table)->where($key)->delete();
  else DB::table($table)->insert($key+['created_at'=>now(),'updated_at'=>now()]);
  return ['saved'=>!$exists,'message'=>$exists?'Removed.':'Saved.'];
 }
 public function report(Request $r,Event $event){
  abort_unless($event->status==='published',404);
  $data=$r->validate([
   'category'=>'required|in:misleading,spam,inappropriate,safety,other',
   'reason'=>'required|string|min:10|max:2000',
  ]);
  return DB::transaction(function()use($r,$event,$data){
   $existing=DB::table('event_reports')
    ->where('event_id',$event->id)->where('user_id',$r->user()->id)
    ->where('category',$data['category'])->where('reason',$data['reason'])
    ->where('status','pending')->lockForUpdate()->first();
   if($existing)return response()->json(['message'=>'This report is already pending review.','report_id'=>$existing->id],200);

   $submittedAt=now();
   $reportId=DB::table('event_reports')->insertGetId([
    'event_id'=>$event->id,'user_id'=>$r->user()->id,'category'=>$data['category'],
    'reason'=>$data['reason'],'status'=>'pending','created_at'=>$submittedAt,'updated_at'=>$submittedAt,
   ]);
   $reference='#'.$reportId;
   $fingerprint=hash('sha256',implode('|',[
    $event->id,$r->user()->id,$data['category'],$data['reason'],intdiv($submittedAt->getTimestamp(),600),
   ]));
   $dedupeKey='event-report:submission:'.$fingerprint;
   $adminMessage='Event: '.$event->title.' · Category: '.str_replace('_',' ',$data['category'])
    .' · Submitted: '.$submittedAt->format('M d, Y g:i A').' · Reference: '.$reference;
   Hub::notifyAdmins('event_report','New event report '.$reference,$adminMessage,'/admin?tab=reports',$dedupeKey.':admins');
   Hub::notify($r->user(),'event_report','Report submitted',
    'Your report for '.$event->title.' was submitted and is pending review. Reference '.$reference.'.',
    '/notifications',$dedupeKey.':reporter');
   Hub::log($r->user(),'event.reported','event_report',$reportId,['event_id'=>$event->id,'category'=>$data['category']]);

   return response()->json(['message'=>'Report submitted for admin review.','report_id'=>$reportId],201);
  });
 }
 public function myReports(Request $r){
  return DB::table('event_reports as reports')
   ->leftJoin('events','events.id','=','reports.event_id')
   ->where('reports.user_id',$r->user()->id)
   ->select(
    'reports.id','reports.event_id','reports.category','reports.reason','reports.status','reports.created_at',
    'events.title as event_title','events.starts_at as event_starts_at','events.venue as event_venue'
   )
   ->orderByDesc('reports.created_at')
   ->paginate(20);
 }
}
