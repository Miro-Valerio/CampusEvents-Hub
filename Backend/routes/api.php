<?php

use App\Http\Controllers\Api\AccountInvitationController;
use App\Http\Controllers\Api\AdminAnnouncementController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminDepartmentController;
use App\Http\Controllers\Api\AdminEventController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentMembershipController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizerAnnouncementController;
use App\Http\Controllers\Api\OrganizerController;
use App\Http\Controllers\Api\OrganizerDashboardController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureOrganizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    $user = $request->user()->load([
        'organizer.department',
        'departmentRecord.designatedOrganizer',
        'departmentRecord.organizers.department',
    ]);
    if ($user->role === 'organizer'
        && $user->departmentRecord?->organizer_user_id === $user->id
        && $user->departmentRecord->organizers->isNotEmpty()) {
        $user->setRelation('organizer', $user->departmentRecord->organizers->first());
    }

    return $user;
})->middleware(['auth:sanctum', EnsureActiveUser::class]);

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::get('/account-invitations/{token}', [AccountInvitationController::class, 'show']);
Route::post('/account-invitations/complete', [AccountInvitationController::class, 'complete'])->middleware('throttle:6,1');
Route::get('/departments', [DepartmentMembershipController::class, 'options']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/past', [EventController::class, 'past']);
Route::get('/events/calendar', [EventController::class, 'calendar']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/events/{event}/attendees', [EventController::class, 'attendees']);
Route::get('/organizers', [OrganizerController::class, 'index']);
Route::get('/organizers/{organizer}', [OrganizerController::class, 'show']);
Route::get('/announcements', [AnnouncementController::class, 'index']);

Route::middleware(['auth:sanctum', EnsureActiveUser::class])->group(function () {
    Route::get('/announcements/unread', [AnnouncementController::class, 'unread']);
    Route::post('/announcements/{announcement}/read', [AnnouncementController::class, 'markRead']);
    Route::post('/announcements/{announcement}/react', [AnnouncementController::class, 'react']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::get('/notification-preferences', [UserSettingsController::class, 'preferences']);
    Route::put('/notification-preferences', [UserSettingsController::class, 'updatePreferences']);
    Route::get('/department-membership', [DepartmentMembershipController::class, 'show']);
    Route::post('/department-membership-requests', [DepartmentMembershipController::class, 'requestMembership']);
    Route::put('/department-membership', [DepartmentMembershipController::class, 'joinAsFaculty']);
    Route::match(['put', 'post'], '/profile', [UserSettingsController::class, 'updateProfile']);
    Route::put('/profile/password', [UserSettingsController::class, 'changePassword']);
    Route::get('/my-rsvps', [EventController::class, 'myRsvps']);
    Route::get('/my-checkins', [EventController::class, 'myCheckIns']);
    Route::get('/my-saves', [EventController::class, 'mySaves']);
    Route::get('/my-calendar', [EventController::class, 'myCalendar']);
    Route::post('/events/{event}/calendar', [EventController::class, 'toggleCalendar']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/events/{event}/rsvp', [EventController::class, 'storeRsvp']);
    Route::delete('/events/{event}/rsvp', [EventController::class, 'cancelRsvp']);
    Route::post('/events/{event}/save', [EventController::class, 'toggleSave']);
    Route::post('/events/{event}/reports', [EventController::class, 'report'])->middleware('throttle:5,60');

    Route::prefix('admin')->middleware(EnsureAdmin::class)->group(function () {
        Route::get('/overview', [AdminController::class, 'overview']);
        Route::get('/analytics', [AdminController::class, 'analytics']);
        Route::get('/activity', [AdminController::class, 'activity']);
        Route::get('/settings', [AdminController::class, 'settings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'deactivate']);
        Route::post('/users/{user}/invitation', [AdminUserController::class, 'resendInvitation']);

        Route::apiResource('departments', AdminDepartmentController::class)->except(['create', 'edit']);
        Route::get('/events', [AdminEventController::class, 'index']);
        Route::get('/events/{eventId}', [AdminEventController::class, 'show'])->whereNumber('eventId');
        Route::put('/events/{eventId}', [AdminEventController::class, 'update'])->whereNumber('eventId');
        Route::post('/events/{eventId}/unpublish', [AdminEventController::class, 'unpublish'])->whereNumber('eventId');
        Route::post('/events/{eventId}/restore', [AdminEventController::class, 'restore'])->whereNumber('eventId');
        Route::delete('/events/{eventId}', [AdminEventController::class, 'destroy'])->whereNumber('eventId');
        Route::apiResource('announcements', AdminAnnouncementController::class)->except(['create', 'edit', 'show']);

        Route::get('/reports', [AdminController::class, 'reports']);
        Route::put('/reports/{report}', [AdminController::class, 'reviewReport']);
    });

    Route::prefix('organizer')->middleware(EnsureOrganizer::class)->group(function () {
        Route::get('/department-membership-requests', [DepartmentMembershipController::class, 'requestsForOrganizer']);
        Route::put('/department-membership-requests/{membershipRequest}', [DepartmentMembershipController::class, 'review']);
        Route::get('/announcements', [OrganizerAnnouncementController::class, 'index']);
        Route::post('/announcements', [OrganizerAnnouncementController::class, 'store']);
        Route::put('/announcements/{announcement}', [OrganizerAnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [OrganizerAnnouncementController::class, 'destroy']);
        Route::post('/announcements/{announcement}/pin', [OrganizerAnnouncementController::class, 'togglePin']);
        Route::get('/events', [OrganizerDashboardController::class, 'index']);
        Route::post('/events', [OrganizerDashboardController::class, 'store']);
        Route::put('/events/{event}', [OrganizerDashboardController::class, 'update']);
        Route::delete('/events/{event}', [OrganizerDashboardController::class, 'destroy']);
        Route::post('/events/{event}/cancel', [OrganizerDashboardController::class, 'cancel']);
        Route::get('/events/{event}/attendees', [OrganizerDashboardController::class, 'attendees']);
        Route::post('/events/{event}/check-in', [OrganizerDashboardController::class, 'checkIn'])->middleware('throttle:30,1');
        Route::post('/events/{event}/photos', [OrganizerDashboardController::class, 'storePhoto']);
        Route::delete('/photos/{photo}', [OrganizerDashboardController::class, 'destroyPhoto']);
        Route::get('/stats', [OrganizerDashboardController::class, 'stats']);
        Route::put('/profile', [OrganizerDashboardController::class, 'updateProfile']);
    });
});
