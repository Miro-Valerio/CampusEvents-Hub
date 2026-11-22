<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
    public function register(): void {}
    public function boot(): void {\Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn($user,$token)=>rtrim(env('FRONTEND_URL','http://localhost:5173'),'/').'/reset-password?token='.$token.'&email='.urlencode($user->email));}
}
