<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Department extends Model {
 use \Illuminate\Database\Eloquent\Factories\HasFactory;
 protected $fillable=['name','code','description','email'];
 public function users(){return $this->hasMany(User::class);}
 public function events(){return $this->hasMany(Event::class);}
}
