<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens, Notifiable;
    protected $fillable = ['name','email','password','course','department_id','role','photo_path','preferences','officer_permissions','faculty_position','is_active'];
    protected $hidden = ['password','remember_token','pending_email'];
    protected function casts(): array {return ['password'=>'hashed','department_id'=>'integer','email_verified_at'=>'datetime','preferences'=>'array','officer_permissions'=>'array','is_active'=>'boolean'];}
    protected $appends = ['photo_url'];
    public function getPhotoUrlAttribute(){return $this->photo_path ? asset('storage/'.$this->photo_path) : null;}
    public function department(){return $this->belongsTo(Department::class);}
    public function memberships(){return $this->hasMany(Membership::class);}
    public function canDepartment(string $ability, ?int $departmentId): bool {
        if($this->role==='admin') return true;
        if(!$departmentId || $this->department_id !== $departmentId) return false;
        if($this->role==='president') return true;
        if($this->role==='faculty' && !in_array($ability,['events','announcements'],true)) return false;
        return in_array($this->role,['officer','faculty'],true)
            && in_array($ability, $this->officer_permissions ?? [], true);
    }
}
