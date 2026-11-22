<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('requires_check_out')->default(false);
            $table->dateTime('check_out_opens_at')->nullable();
            $table->dateTime('check_out_closes_at')->nullable();
            $table->string('check_out_code', 12)->nullable();
        });

        Schema::table('event_check_ins', function (Blueprint $table) {
            $table->string('verification_status', 20)->nullable()->index();
            $table->timestamp('verification_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['verification_status']);
            $table->dropColumn(['verification_status', 'verification_at', 'checked_out_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'requires_check_out',
                'check_out_opens_at',
                'check_out_closes_at',
                'check_out_code',
            ]);
        });
    }
};
