<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('availability_preference', 10)->default('online');
            $table->timestamp('last_active_at')->nullable();
            $table->index(['role', 'is_active', 'last_active_at'], 'users_admin_presence_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_admin_presence_idx');
            $table->dropColumn(['availability_preference', 'last_active_at']);
        });
    }
};
