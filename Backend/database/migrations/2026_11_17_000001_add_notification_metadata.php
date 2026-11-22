<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_reports', function (Blueprint $table) {
            $table->string('category', 40)->default('other')->after('event_id');
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('dedupe_key', 191)->nullable()->after('user_id');
            $table->unique(['user_id', 'dedupe_key'], 'user_notifications_user_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropUnique('user_notifications_user_dedupe_unique');
            $table->dropColumn('dedupe_key');
        });

        Schema::table('event_reports', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
