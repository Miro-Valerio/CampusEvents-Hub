<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
class Event extends Model {
 use SoftDeletes;
 protected $fillable=['department_id','created_by','title','description','venue','starts_at','ends_at','category','seat_limit','poster_path','rules','status','check_in_code','requires_check_out','check_out_opens_at','check_out_closes_at','check_out_code'];
 protected $appends=['poster_url','schedule_status'];
 protected $hidden=['check_in_code','check_out_code'];
 protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime','check_out_opens_at'=>'datetime','check_out_closes_at'=>'datetime','requires_check_out'=>'boolean','seat_limit'=>'integer','views'=>'integer'];}
 public function department(){return $this->belongsTo(Department::class);}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
 public function rsvps(){return $this->hasMany(Rsvp::class);}
 public function getPosterUrlAttribute(){return $this->poster_path ? asset('storage/'.$this->poster_path) : null;}
 public function getScheduleStatusAttribute():string {
  if($this->status==='cancelled')return 'Cancelled';
  $now=now();
  if($this->ends_at && $this->ends_at->lessThan($now))return 'Ended';
  if($this->starts_at && $this->starts_at->lessThanOrEqualTo($now))return 'Ongoing';
  return 'Upcoming';
 }
}
