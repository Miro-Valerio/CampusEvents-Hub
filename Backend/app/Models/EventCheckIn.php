<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EventCheckIn extends Model {
 protected $table='event_check_ins';
 protected $fillable=['rsvp_id','verified_by','reviewed_by','verification_status','verification_at','checked_at','checked_out_at'];
 protected function casts():array{return ['checked_at'=>'datetime','verification_at'=>'datetime','checked_out_at'=>'datetime'];}
 public function rsvp(){return $this->belongsTo(Rsvp::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
}
