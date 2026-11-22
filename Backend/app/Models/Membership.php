<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Membership extends Model {
 protected $fillable=['user_id','department_id','status','reviewed_by','reviewed_at'];
 protected function casts():array{return ['reviewed_at'=>'datetime'];}
 public function user(){return $this->belongsTo(User::class);}
 public function department(){return $this->belongsTo(Department::class);}
}
