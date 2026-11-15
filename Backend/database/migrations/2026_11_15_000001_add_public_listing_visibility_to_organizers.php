<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->boolean('is_listed')->default(true)->index();
        });

        DB::table('organizers')
            ->whereIn('name', ['Arts & Culture Guild', 'IT Student Organization'])
            ->update(['is_listed' => false]);
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropColumn('is_listed');
        });
    }
};
