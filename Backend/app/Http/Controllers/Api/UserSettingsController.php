<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserSettingsController extends Controller
{
    private const DEPARTMENTS = ['CCS', 'BSCRIM', 'COE', 'CED', 'CAD', 'CBA'];

    private const DEFAULT_PREFERENCES = [
        'event_reminders' => true,
        'event_changes' => true,
        'announcements' => true,
    ];

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => array_replace(
                self::DEFAULT_PREFERENCES,
                $request->user()->notification_preferences ?? [],
            ),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_reminders' => ['sometimes', 'boolean'],
            'event_changes' => ['sometimes', 'boolean'],
            'announcements' => ['sometimes', 'boolean'],
        ]);

        $preferences = array_replace(
            self::DEFAULT_PREFERENCES,
            $request->user()->notification_preferences ?? [],
            $data,
        );
        $request->user()->forceFill(['notification_preferences' => $preferences])->save();

        return response()->json(['preferences' => $preferences]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'course' => [
                'nullable',
                'string',
                Rule::in([
                    'BS Information Technology',
                    'BS Computer Science',
                    'BS Business Administration',
                    'BS Accountancy',
                    'BS Criminology',
                    'BS Education',
                    'BS Civil Engineering',
                    'BS Computer Engineering',
                    'BS Electrical Engineering',
                    'BS Electronics Engineering',
                    'BS Mechanical Engineering',
                ]),
            ],
            'department' => ['nullable', 'string', Rule::in(self::DEPARTMENTS)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $oldPhotoUrl = $user->profile_photo_url;
        unset($data['profile_photo']);
        if ($user->role !== 'student') {
            $data['course'] = null;
            $data['department'] = null;
        }
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->storePublicly('user-profiles', 'public');
            if ($path === false) {
                throw new \RuntimeException('The profile photo could not be stored.');
            }
            $data['profile_photo_url'] = Storage::disk('public')->url($path);
        }

        $user->update($data);
        if ($request->hasFile('profile_photo')) {
            $this->deleteStoredPhoto($oldPhotoUrl);
        }

        return response()->json($user->fresh()->load('organizer'));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $request->user()->forceFill(['password' => $data['new_password']])->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    private function deleteStoredPhoto(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/storage/user-profiles/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }
}
