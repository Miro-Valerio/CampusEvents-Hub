<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $registrationEnabled = DB::table('system_settings')->where('key', 'public_registration_enabled')->value('value');
        if ($registrationEnabled !== null && json_decode($registrationEnabled, true) === false) {
            return response()->json(['message' => 'Public registration is currently unavailable.'], 403);
        }
        $data = $request->validated();
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'course' => $data['course'] ?? null,
                'department' => $data['department'],
            ]);
            $user->role = 'student';
            $user->save();

            return $user->load(['organizer.department', 'organizations.department']);
        });

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('campus-events-web')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The email or password is incorrect.']]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['This account is inactive. Contact the system administrator.']]);
        }

        $user->load(['organizer.department', 'organizations.department']);
        if (! $user->organizer && $user->organizations->isNotEmpty()) {
            $user->setRelation('organizer', $user->organizations->first());
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('campus-events-web')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
