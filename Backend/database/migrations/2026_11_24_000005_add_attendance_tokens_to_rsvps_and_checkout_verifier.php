<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->text('attendance_token')->nullable();
            $table->char('attendance_code_hash', 64)->nullable()->unique();
        });

        Schema::table('event_check_ins', function (Blueprint $table) {
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_out_by');
        });

        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropUnique(['attendance_code_hash']);
            $table->dropColumn(['attendance_token', 'attendance_code_hash']);
        });
    }
};
