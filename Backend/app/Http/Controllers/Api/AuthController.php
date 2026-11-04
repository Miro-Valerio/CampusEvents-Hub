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
        $data = $request->validated();
        $role = $data['role'] ?? 'student';
        $user = DB::transaction(function () use ($data, $role): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'course' => $role === 'student' ? ($data['course'] ?? null) : null,
            ]);
            $user->role = $role;
            $user->save();

            if ($role === 'organizer') {
                $user->organizer()->create([
                    'name' => $data['organization_name'],
                    'description' => $data['description'],
                ]);
            }

            return $user->load('organizer');
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

        return response()->json([
            'user' => $user->load('organizer'),
            'token' => $user->createToken('campus-events-web')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
