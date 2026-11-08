<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AccountInvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = $this->findValidInvitation($token);
        if (! $invitation) {
            return response()->json(['message' => 'This account setup link is invalid, expired, or already used.'], 410);
        }

        return response()->json([
            'name' => $invitation->user->name,
            'email' => $invitation->user->email,
            'role' => $invitation->user->role,
            'department' => $invitation->user->department,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', Password::min($this->minimumPasswordLength()), 'confirmed'],
        ]);

        $invitation = $this->findValidInvitation($data['token']);
        if (! $invitation) {
            return response()->json(['message' => 'This account setup link is invalid, expired, or already used.'], 410);
        }

        DB::transaction(function () use ($invitation, $data): void {
            $locked = UserInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->used_at || $locked->expires_at->isPast()) {
                abort(410, 'This account setup link is invalid, expired, or already used.');
            }

            $user = $locked->user;
            $user->forceFill([
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
            $locked->update(['used_at' => now()]);
        });

        return response()->json(['message' => 'Your password is set and your account is active. You can now sign in.']);
    }

    private function findValidInvitation(string $token): ?UserInvitation
    {
        return UserInvitation::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function minimumPasswordLength(): int
    {
        $configured = DB::table('system_settings')->where('key', 'minimum_password_length')->value('value');
        $decoded = $configured === null ? 12 : json_decode($configured, true);

        return is_int($decoded) ? max(12, $decoded) : 12;
    }
}
