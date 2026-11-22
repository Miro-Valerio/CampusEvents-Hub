<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureRole {
 public function handle(Request $request, Closure $next, string ...$roles){
  abort_unless(in_array($request->user()?->role,$roles,true),403,'You do not have permission to access this page.');
  return $next($request);
 }
}
