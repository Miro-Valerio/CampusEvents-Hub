<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->string('check_in_code_salt', 32)->nullable();
        });

        DB::table('rsvps')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($rsvps): void {
                foreach ($rsvps as $rsvp) {
                    DB::table('rsvps')
                        ->where('id', $rsvp->id)
                        ->update(['check_in_code_salt' => bin2hex(random_bytes(16))]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropColumn('check_in_code_salt');
        });
    }
};
