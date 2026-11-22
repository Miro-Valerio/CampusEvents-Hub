<?php
namespace App\Services;
use App\Models\{Membership,User,UserNotification};
use Illuminate\Support\Facades\DB;
class Hub {
 public static function notify(?User $user,string $type,string $title,string $message,?string $link=null,?string $dedupeKey=null):void {
  if(!$user || !$user->is_active) return;
  $pref=$user->preferences ?? [];
  if(($pref['notifications_enabled']??true)===false) return;
  if($type==='reminder' && ($pref['reminders_enabled']??true)===false) return;
  if($type==='announcement' && ($pref['announcement_alerts_enabled']??true)===false) return;
  $attributes=compact('type','title','message','link');
  if($dedupeKey!==null) {
   UserNotification::firstOrCreate(['user_id'=>$user->id,'dedupe_key'=>$dedupeKey],$attributes);
   return;
  }
  UserNotification::create($attributes+['user_id'=>$user->id]);
 }
 public static function notifyAdmins(string $type,string $title,string $message,?string $link,string $dedupeKey,?int $exceptUserId=null):void {
  User::where('role','admin')->orderBy('id')->chunkById(100,function($users)use($type,$title,$message,$link,$dedupeKey,$exceptUserId){
   foreach($users as $user){
    if($user->id===$exceptUserId)continue;
    self::notify($user,$type,$title,$message,$link,$dedupeKey.':'.$user->id);
   }
  });
 }
 public static function notifyDepartmentManagers(int $departmentId,string $ability,string $type,string $title,string $message,?string $link,string $dedupeKey,?int $exceptUserId=null):void {
  User::where('is_active',true)->where('department_id',$departmentId)->whereIn('role',['president','officer','faculty'])->orderBy('id')->chunkById(100,function($users)use($departmentId,$ability,$type,$title,$message,$link,$dedupeKey,$exceptUserId){
   foreach($users as $user){
    if($user->id===$exceptUserId || !$user->canDepartment($ability,$departmentId))continue;
    self::notify($user,$type,$title,$message,$link,$dedupeKey.':'.$user->id);
   }
  });
 }
 public static function notifyDepartmentStudents(int $departmentId,string $type,string $title,string $message,?string $link,string $dedupeKey,?int $exceptUserId=null):void {
  $approvedMemberships=Membership::where('department_id',$departmentId)->where('status','approved')->select('user_id');
  User::where('is_active',true)->where('role','student')->where(function($query)use($departmentId,$approvedMemberships){
   $query->where('department_id',$departmentId)->orWhereIn('id',$approvedMemberships);
  })->where('id','!=',$exceptUserId??0)->orderBy('id')->chunkById(100,function($users)use($type,$title,$message,$link,$dedupeKey){
   foreach($users as $user)self::notify($user,$type,$title,$message,$link,$dedupeKey.':'.$user->id);
  });
 }
 public static function notifyDepartmentAudience(int $departmentId,?string $course,string $type,string $title,string $message,?string $link,string $dedupeKey,?int $exceptUserId=null):void {
  $approvedMemberships=Membership::where('department_id',$departmentId)->where('status','approved')->select('user_id');
  $query=User::where('is_active',true)->whereIn('role',['student','faculty'])->where(function($audience)use($departmentId,$approvedMemberships){
   $audience->where('department_id',$departmentId)->orWhereIn('id',$approvedMemberships);
  });
  if($course!==null)$query->where(function($matchingCourse)use($course){
   $matchingCourse->where('role','faculty')->orWhere('course',$course);
  });
  if($exceptUserId!==null)$query->where('id','!=',$exceptUserId);
  $query->orderBy('id')->chunkById(100,function($users)use($departmentId,$type,$title,$message,$link,$dedupeKey){
   $managerAbility=match($type){'event'=>'events','announcement'=>'announcements',default=>null};
   foreach($users as $user){
    if($managerAbility && $user->canDepartment($managerAbility,$departmentId))continue;
    self::notify($user,$type,$title,$message,$link,$dedupeKey.':'.$user->id);
   }
  });
 }
 public static function notifyDepartmentMembers(int $departmentId,?string $course,string $type,string $title,string $message,?string $link,string $dedupeKey,?int $exceptUserId=null):void {
  $approvedMemberships=Membership::where('department_id',$departmentId)->where('status','approved')->select('user_id');
  $query=User::where('is_active',true)->where(function($query)use($departmentId,$approvedMemberships){
   $query->where('department_id',$departmentId)->orWhereIn('id',$approvedMemberships);
  });
  if($course!==null)$query->where('course',$course);
  if($exceptUserId!==null)$query->where('id','!=',$exceptUserId);
  $query->orderBy('id')->chunkById(100,function($users)use($type,$title,$message,$link,$dedupeKey){
   foreach($users as $user)self::notify($user,$type,$title,$message,$link,$dedupeKey.':'.$user->id);
  });
 }
 public static function log(?User $actor,string $action,?string $subjectType=null,?int $subjectId=null,array $details=[]):void {
  DB::table('audit_logs')->insert(['actor_id'=>$actor?->id,'action'=>$action,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function canManage(User $user, string $ability, int $departmentId):void {
  abort_unless($user->canDepartment($ability,$departmentId),403,'You are not authorized for this department or action.');
 }
}
