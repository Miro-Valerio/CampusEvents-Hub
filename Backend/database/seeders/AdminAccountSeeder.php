<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.bootstrap_email');
        $password = config('admin.bootstrap_password');
        if (! $email && ! $password) {
            return;
        }
        if (! $email || ! $password || strlen($password) < 8) {
            throw new RuntimeException('Set BOOTSTRAP_ADMIN_EMAIL and a BOOTSTRAP_ADMIN_PASSWORD with at least 8 characters.');
        }

        $admin = User::firstOrNew(['email' => $email]);
        $isNew = ! $admin->exists;
        $admin->name = 'System Administrator';
        if ($isNew) {
            $admin->password = Hash::make($password);
        }
        $admin->department = null;
        $admin->course = null;
        $admin->forceFill(['role' => 'admin'])->save();
    }
}
