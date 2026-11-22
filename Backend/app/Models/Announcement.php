<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Announcement extends Model {
 protected $fillable=['department_id','created_by','title','body','target_course','is_pinned'];
 protected function casts():array{return ['is_pinned'=>'boolean'];}
 public function department(){return $this->belongsTo(Department::class);}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
}
