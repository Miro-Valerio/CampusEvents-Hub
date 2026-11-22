<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => response()->json(['name' => 'SPC CampusEvents Hub API', 'status'=>'ok']));
