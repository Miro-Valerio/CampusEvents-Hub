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
  $bookmarks=Event::with('department')->whereIn('id',DB::table('bookmarks')->where('user_id',$user->id)->pluck('event_id'))->get();
  $calendar=Event::with('department')->whereIn('id',DB::table('calendar_entries')->where('user_id',$user->id)->pluck('event_id'))->get();
  return ['rsvps'=>$rsvps,'bookmarks'=>$bookmarks,'calendar'=>$calendar];
 }
 public function rsvp(Request $r,Event $event){
  abort_unless(in_array($r->user()->role,['student','faculty'],true),403,'Only students and Faculty can RSVP.');
  abort_unless($event->status==='published',404);
  return DB::transaction(function()use($r,$event){
   $event=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   abort_unless($event->status==='published' && $event->starts_at->isFuture(),422,'This event is not open for RSVP.');
   $existing=Rsvp::where('event_id',$event->id)->where('user_id',$r->user()->id)->first();
   if($existing?->status==='active')return response()->json(['message'=>'You already RSVP’d for this event.','rsvp'=>$existing],422);
   $taken=Rsvp::where('event_id',$event->id)->where('status','active')->pluck('seat_no')->all();
   if(count($taken)>=$event->seat_limit)return response()->json(['message'=>'This event is full.'],422);
   $set=array_fill_keys($taken,true);$seat=1;
   while(isset($set[$seat]) && $seat<=$event->seat_limit)$seat++;
   $rsvp=Rsvp::updateOrCreate(['event_id'=>$event->id,'user_id'=>$r->user()->id],['status'=>'active','seat_no'=>$seat,'reminder_sent_at'=>null]);
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
   $row->update(['status'=>'cancelled','seat_no'=>null,'reminder_sent_at'=>null]);
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
 public function checkin(Request $r,Event $event){
  abort_unless(in_array($r->user()->role,['student','faculty'],true),403,'Only students and Faculty can check in.');
  $data=$r->validate(['code'=>'required|string|max:12']);
  return DB::transaction(function()use($r,$event,$data){
   $event=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   abort_unless($event->status==='published',404);
   abort_unless(now()->between($event->starts_at->copy()->subHours(2),$event->ends_at),422,'Check-in is not open for this event.');
   abort_unless($event->check_in_code && hash_equals(strtoupper($event->check_in_code),strtoupper(trim($data['code']))),422,'Invalid check-in code.');
   $rsvp=Rsvp::where('event_id',$event->id)->where('user_id',$r->user()->id)->where('status','active')->lockForUpdate()->firstOrFail();
   abort_if(EventCheckIn::where('rsvp_id',$rsvp->id)->lockForUpdate()->exists(),422,'Already checked in.');
   $checkin=EventCheckIn::create([
    'rsvp_id'=>$rsvp->id,
    'verification_status'=>'pending',
    'checked_at'=>now(),
   ]);
   $key='event-checkin:'.$checkin->id;
   Hub::notify($r->user(),'checkin','Check-in submitted',
    'Your check-in for '.$event->title.' is pending organizer verification.',
    '/events/'.$event->id,$key.':student');
   if($event->department_id){
    Hub::notifyDepartmentManagers($event->department_id,'events','checkin','Check-in awaiting verification',
     $r->user()->name.' submitted a check-in for '.$event->title.'.',
     '/organizer?tab=attendees&department_id='.$event->department_id,$key.':managers',$r->user()->id);
   }elseif($event->created_by!==$r->user()->id){
    Hub::notify(User::find($event->created_by),'checkin','Check-in awaiting verification',
     $r->user()->name.' submitted a check-in for '.$event->title.'.','/organizer?tab=attendees',$key.':organizer');
   }
   return ['message'=>'Check-in submitted successfully. Pending verification.','attendance_status'=>'Pending Verification'];
  });
 }
 public function checkout(Request $r,Event $event){
  abort_unless(in_array($r->user()->role,['student','faculty'],true),403,'Only students and Faculty can check out.');
  $data=$r->validate(['code'=>'required|string|max:12']);
  return DB::transaction(function()use($r,$event,$data){
   $event=Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
   abort_unless($event->status==='published'&&$event->requires_check_out,422,'Check-out is not required for this event.');
   abort_unless($event->check_out_opens_at&&$event->check_out_closes_at&&now()->between($event->check_out_opens_at,$event->check_out_closes_at),422,'Check-out is not open for this event.');
   abort_unless($event->check_out_code&&hash_equals(strtoupper($event->check_out_code),strtoupper(trim($data['code']))),422,'Invalid check-out code.');
   $rsvp=Rsvp::where('event_id',$event->id)->where('user_id',$r->user()->id)->where('status','active')->lockForUpdate()->firstOrFail();
   $checkin=EventCheckIn::where('rsvp_id',$rsvp->id)->lockForUpdate()->firstOrFail();
   abort_unless($checkin->verification_status==='verified',422,'Check-in must be verified before check-out.');
   abort_if($checkin->checked_out_at,422,'Check-out was already recorded.');
   $checkin->update(['checked_out_at'=>now()]);
   $key='event-checkout:'.$checkin->id;
   Hub::notify($r->user(),'checkin','Attendance completed',
    'Your attendance for '.$event->title.' is complete.','/events/'.$event->id,$key.':student');
   return ['message'=>'Check-out recorded. Your attendance is complete.','attendance_status'=>'Completed'];
  });
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
}
