<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash,Password,DB,Mail,URL};
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller {
 public function register(Request $r){
  $data=$r->validate([
   'name'=>'required|string|max:120','email'=>'required|email|max:255|unique:users,email',
   'course'=>'nullable|string|max:140',
   'year_level'=>['required',Rule::in(['1st Year','2nd Year','3rd Year','4th Year'])],
   'department_id'=>'required|exists:departments,id',
   'password'=>['required','confirmed',PasswordRule::min(8)],
  ]);
  if(empty($data['course'])){
   abort(422,'Select a course or program.');
  }
  abort_if(DB::table('system_settings')->where('key','registration_open')->value('value')==='0',403,'Student registration is currently closed.');
  $user=User::create($data+['role'=>'student','preferences'=>['notifications_enabled'=>true,'reminders_enabled'=>true,'announcement_alerts_enabled'=>true]]);
  Hub::log($user,'account.registered','user',$user->id);
  return response()->json(['user'=>$user->load(['department']),'token'=>$user->createToken('campus-events')->plainTextToken],201);
 }
 public function login(Request $r){
  $data=$r->validate(['email'=>'required|email','password'=>'required|string']);
  $user=User::where('email',$data['email'])->first();
  if(!$user || !Hash::check($data['password'],$user->password)) return response()->json(['message'=>'Invalid email or password.'],422);
  abort_unless($user->is_active,403,'This account is deactivated. Contact an administrator.');
  return ['user'=>$user->load(['department']),'token'=>$user->createToken('campus-events')->plainTextToken];
 }
 public function logout(Request $r){
  $user=$r->user();
  $user->currentAccessToken()?->delete();
  if($user->role==='admin'&&!$user->tokens()->exists()){
   $user->forceFill(['last_active_at'=>null])->save();
  }
  return ['message'=>'Logged out.'];
 }
 public function me(Request $r){return $r->user()->load(['department']);}
 public function forgot(Request $r){
  $r->validate(['email'=>'required|email']);
  Password::sendResetLink($r->only('email'));
  return ['message'=>'If this account exists, a password reset link has been sent. Check your mailbox (or storage/logs/laravel.log when MAIL_MAILER=log).'];
 }
 public function reset(Request $r){
  $data=$r->validate(['token'=>'required|string','email'=>'required|email','password'=>['required','confirmed',PasswordRule::min(8)]]);
  $status=Password::reset($data,function(User $user,string $password){
   $updates=[
    'password'=>Hash::make($password),
    'remember_token'=>Str::random(60),
    'email_verified_at'=>$user->email_verified_at??now(),
    'must_change_password'=>false,
   ];
   if($user->activation_pending){
    $updates['is_active']=true;
    $updates['activation_pending']=false;
   }
   $user->forceFill($updates)->save();
   $user->tokens()->delete(); event(new PasswordReset($user));
  });
  return $status===Password::PASSWORD_RESET ? ['message'=>'Password updated. Log in with your new password.'] : response()->json(['message'=>__($status)],422);
 }
 public function profile(Request $r){
  $user=$r->user();
  $data=$r->validate(['name'=>'sometimes|required|string|max:120',
   'course'=>$user->role==='student'?'sometimes|required|string|max:140':'sometimes|nullable|string|max:140',
   'photo'=>'sometimes|nullable|image|max:3072']);
  if($r->hasFile('photo')){
   if($user->photo_path) \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo_path);
   $data['photo_path']=$r->file('photo')->store('profiles','public');
  }
  unset($data['photo']); $user->update($data);
  return $user->fresh()->load(['department']);
 }
 public function requestEmailChange(Request $r){
  $user=$r->user();
  $data=$r->validate([
   'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user->id),Rule::unique('users','pending_email')->ignore($user->id)],
   'current_password'=>'required|string',
  ]);
  abort_unless(Hash::check($data['current_password'],$user->password),422,'Current password is incorrect.');
  $email=strtolower($data['email']);
  abort_if(strcasecmp($email,$user->email)===0,422,'Enter an email address different from your current one.');
  $user->forceFill(['pending_email'=>$email])->save();
  $url=URL::temporarySignedRoute('email.change.verify',now()->addHour(),[
   'user'=>$user->id,
   'email'=>hash('sha256',$email),
  ]);
  try{
   Mail::raw("Confirm your new CampusEvents Hub email address by opening this link within 60 minutes:\n\n".$url,
    function($mail)use($email){$mail->to($email)->subject('Confirm your CampusEvents Hub email address');});
  }catch(\Throwable $exception){
   $user->forceFill(['pending_email'=>null])->save();
   throw $exception;
  }
  return ['message'=>'A confirmation link has been sent to the new email address. Your current email remains active until you confirm it.'];
 }
 public function verifyEmailChange(Request $r){
  $user=User::findOrFail($r->integer('user'));
  $pendingEmail=$user->pending_email;
  abort_unless($pendingEmail && hash_equals(hash('sha256',$pendingEmail),(string)$r->query('email')),403,'This email confirmation link is invalid or expired.');
  abort_if(User::where('email',$pendingEmail)->where('id','<>',$user->id)->exists(),409,'That email address is no longer available.');
  $user->forceFill(['email'=>$pendingEmail,'pending_email'=>null,'email_verified_at'=>now()])->save();
  return redirect(rtrim(config('app.frontend_url'),'/').'/profile?email=verified');
 }
 public function changePassword(Request $r){
  $user=$r->user();
  $passwordRule=$user->must_change_password
   ?PasswordRule::min(8)->mixedCase()->numbers()->symbols()
   :PasswordRule::min(8);
  $data=$r->validate(['current_password'=>'required|string','password'=>['required','confirmed',$passwordRule]]);
  abort_unless(Hash::check($data['current_password'],$user->password),422,'Current password is incorrect.');
  $user->update(['password'=>$data['password'],'must_change_password'=>false]);
  $user->tokens()->where('id','!=',$user->currentAccessToken()?->id)->delete();
  return ['message'=>'Password updated.'];
 }
 public function preferences(Request $r){
  $data=$r->validate(['notifications_enabled'=>'sometimes|boolean','reminders_enabled'=>'sometimes|boolean','announcement_alerts_enabled'=>'sometimes|boolean']);
  $user=$r->user();$user->update(['preferences'=>array_merge($user->preferences??[],$data)]);
  return $user->fresh();
 }
}
