<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserNotification extends Model {
 protected $fillable=['user_id','dedupe_key','type','title','message','link','read_at'];
 protected function casts():array{return ['read_at'=>'datetime'];}
}
