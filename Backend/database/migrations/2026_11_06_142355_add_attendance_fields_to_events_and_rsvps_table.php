<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('check_in_code', 12)->nullable()->after('capacity');
        });

        $usedCodes = DB::table('events')
            ->whereNotNull('check_in_code')
            ->pluck('check_in_code')
            ->flip()
            ->all();

        DB::table('events')
            ->whereNull('check_in_code')
            ->orderBy('id')
            ->chunkById(100, function ($events) use (&$usedCodes): void {
                foreach ($events as $event) {
                    do {
                        $code = Str::upper(Str::random(8));
                    } while (isset($usedCodes[$code]));

                    $usedCodes[$code] = true;

                    DB::table('events')
                        ->where('id', $event->id)
                        ->update(['check_in_code' => $code]);
                }
            });

        Schema::table('events', function (Blueprint $table) {
            $table->unique('check_in_code');
        });

        Schema::table('rsvps', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('seat_number');
            $table->timestamp('checked_in_at')->nullable()->after('reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'checked_in_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['check_in_code']);
            $table->dropColumn('check_in_code');
        });
    }
};
