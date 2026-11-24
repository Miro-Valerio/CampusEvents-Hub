<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Rsvp extends Model {
 protected $fillable=['event_id','user_id','seat_no','status','reminder_sent_at','attendance_token','attendance_code_hash'];
 protected $hidden=['attendance_token','attendance_code_hash'];
 protected $appends=['attendance_status'];
 protected function casts():array{return ['reminder_sent_at'=>'datetime','attendance_token'=>'encrypted'];}
 public function event(){return $this->belongsTo(Event::class);}
 public function user(){return $this->belongsTo(User::class);}
 public function checkin(){return $this->hasOne(EventCheckIn::class);}
 public function getAttendanceStatusAttribute():string {
  if($this->status!=='active')return 'Not Checked In';
  $checkin=$this->checkin;
  if($checkin){
   if($checkin->verification_status===null)return 'Completed';
   if($checkin->verification_status==='pending')return 'Pending Verification';
   if($checkin->verification_status==='rejected')return 'Rejected';
   if($checkin->verification_status==='verified'){
    if(!$this->event->requires_check_out)return 'Verified';
    if($checkin->checked_out_at)return 'Completed';
    if($this->event->check_out_closes_at && now()->greaterThan($this->event->check_out_closes_at))return 'Absent';
    return 'Verified';
   }
  }
  $event=$this->event;
  if($event->status==='cancelled')return 'Not Checked In';
  $closesAt=$event->requires_check_out&&$event->check_out_closes_at
   ?$event->check_out_closes_at
   :$event->ends_at;
  return $closesAt&&now()->greaterThan($closesAt)?'Absent':'Not Checked In';
 }
}
