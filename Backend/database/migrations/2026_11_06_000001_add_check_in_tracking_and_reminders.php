<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('seat_number');
        });

        Schema::create('event_reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->timestamps();
            $table->unique(['event_id', 'user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminders_sent');

        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropColumn('checked_in_at');
        });
    }
};
