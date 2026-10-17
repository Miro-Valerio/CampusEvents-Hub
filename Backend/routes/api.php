<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\OrganizerController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Middleware\EnsureOrganizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user()->load('organizer');
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/past', [EventController::class, 'past']);
Route::get('/events/calendar', [EventController::class, 'calendar']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/events/{event}/attendees', [EventController::class, 'attendees']);
Route::get('/organizers', [OrganizerController::class, 'index']);
Route::get('/organizers/{organizer}', [OrganizerController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-rsvps', [EventController::class, 'myRsvps']);
    Route::get('/my-saves', [EventController::class, 'mySaves']);
    Route::get('/my-calendar', [EventController::class, 'myCalendar']);
    Route::post('/events/{event}/calendar', [EventController::class, 'toggleCalendar']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/events/{event}/rsvp', [EventController::class, 'storeRsvp']);
    Route::delete('/events/{event}/rsvp', [EventController::class, 'cancelRsvp']);
    Route::post('/events/{event}/save', [EventController::class, 'toggleSave']);

    Route::prefix('organizer')->middleware(EnsureOrganizer::class)->group(function () {
        Route::get('/events', [OrganizerDashboardController::class, 'index']);
        Route::post('/events', [OrganizerDashboardController::class, 'store']);
        Route::put('/events/{event}', [OrganizerDashboardController::class, 'update']);
        Route::delete('/events/{event}', [OrganizerDashboardController::class, 'destroy']);
        Route::get('/events/{event}/attendees', [OrganizerDashboardController::class, 'attendees']);
        Route::post('/events/{event}/photos', [OrganizerDashboardController::class, 'storePhoto']);
        Route::delete('/photos/{photo}', [OrganizerDashboardController::class, 'destroyPhoto']);
        Route::get('/stats', [OrganizerDashboardController::class, 'stats']);
        Route::put('/profile', [OrganizerDashboardController::class, 'updateProfile']);
    });
});
