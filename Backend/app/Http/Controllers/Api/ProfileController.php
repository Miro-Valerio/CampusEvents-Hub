<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->load('organizer'),
            'notification_preferences' => $request->user()->notificationPreferences(),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => $user->fresh()->load('organizer'),
            'message' => 'Profile updated.',
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $previousPath = $this->storedPhotoPath($user->photo_url);
        $path = $request->file('photo')->store('avatars', 'public');

        $user->update(['photo_url' => Storage::disk('public')->url($path)]);

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return response()->json([
            'data' => $user->fresh()->load('organizer'),
            'message' => 'Profile photo updated.',
        ]);
    }

    private function storedPhotoPath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/avatars/')) {
            return null;
        }

        return 'avatars/'.basename($path);
    }
}
