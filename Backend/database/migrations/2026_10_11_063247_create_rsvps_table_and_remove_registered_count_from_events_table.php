<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('registered_count', 'baseline_registered_count');
        });

        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('going');
            $table->unsignedInteger('seat_number')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
            $table->index(['event_id', 'status']);
            $table->unique(['event_id', 'seat_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('registered_count')->default(0);
        });

        foreach (DB::table('events')->get(['id', 'baseline_registered_count']) as $event) {
            $registeredCount = DB::table('rsvps')
                ->where('event_id', $event->id)
                ->where('status', 'going')
                ->count();

            DB::table('events')->where('id', $event->id)->update([
                'registered_count' => $event->baseline_registered_count + $registeredCount,
            ]);
        }

        Schema::dropIfExists('rsvps');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('baseline_registered_count');
        });
    }
};
