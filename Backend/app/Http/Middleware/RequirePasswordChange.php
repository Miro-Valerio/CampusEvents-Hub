<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange {
 public function handle(Request $request,Closure $next){
  if($request->user()?->must_change_password
   && !$request->routeIs('auth.me','auth.logout','auth.password.change')){
   return response()->json(['message'=>'Change your temporary password before continuing.'],403);
  }
  return $next($request);
 }
}
