<?php
namespace Database\Seeders;
use App\Models\{Department,Event,User,Announcement};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class DatabaseSeeder extends Seeder {
 public function run():void {
  foreach([
   ['code'=>'CCS','name'=>'College of Computer Studies','description'=>'Computing, software development, and technology activities.'],
   ['code'=>'CBA','name'=>'College of Business and Accountancy','description'=>'Business, entrepreneurship, finance, and leadership.'],
   ['code'=>'CE','name'=>'College of Education','description'=>'Teaching, learning, and student development.'],
   ['code'=>'CAS','name'=>'College of Arts and Sciences','description'=>'Arts, sciences, and community engagement.'],
  ] as $row)Department::updateOrCreate(['code'=>$row['code']],$row);
  $ccs=Department::where('code','CCS')->firstOrFail();
  $admin=User::firstOrCreate(['email'=>'admin@spc.local'],['name'=>'SPC System Administrator','password'=>'ChangeMe123!','role'=>'admin']);
  $president=User::firstOrCreate(['email'=>'president@spc.local'],['name'=>'CCS Department President','password'=>'ChangeMe123!','role'=>'president','department_id'=>$ccs->id,'course'=>'BS Information Technology']);
  User::firstOrCreate(['email'=>'officer@spc.local'],['name'=>'CCS Events Officer','password'=>'ChangeMe123!','role'=>'officer','department_id'=>$ccs->id,'course'=>'BS Information Technology','officer_permissions'=>['events','announcements','members']]);
  User::firstOrCreate(['email'=>'student@spc.local'],['name'=>'Sample Student','password'=>'ChangeMe123!','role'=>'student','department_id'=>$ccs->id,'course'=>'BS Information Technology']);
  $event=Event::firstOrCreate(['title'=>'SPC Tech and Innovation Fair'],[
   'department_id'=>$ccs->id,'created_by'=>$president->id,'description'=>'Discover student projects, explore emerging technology, and connect with fellow innovators.','venue'=>'SPC Main Hall','starts_at'=>now()->addDays(10)->setTime(9,0),'ends_at'=>now()->addDays(10)->setTime(16,0),'category'=>'Technology','seat_limit'=>120,'rules'=>'Bring your student ID. Arrive 15 minutes early.','status'=>'published','check_in_code'=>Str::upper(Str::random(6))
  ]);
  Announcement::firstOrCreate(['title'=>'Welcome to CampusEvents Hub'],['created_by'=>$admin->id,'body'=>'Explore upcoming campus events, reserve your seat, and stay connected with SPC.','is_pinned'=>true]);
  DB::table('system_settings')->updateOrInsert(['key'=>'campus_name'],['value'=>"St. Peter's College",'updated_at'=>now(),'created_at'=>now()]);
  DB::table('system_settings')->updateOrInsert(['key'=>'registration_open'],['value'=>'1','updated_at'=>now(),'created_at'=>now()]);
 }
}
