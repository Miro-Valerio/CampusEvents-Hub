<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('category')->index();
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->unsignedBigInteger('views_count')->default(0)->after('capacity');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_url', 2048)->nullable()->after('course');
            $table->json('notification_preferences')->nullable()->after('photo_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['photo_url', 'notification_preferences']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'cancelled_at', 'cancellation_reason', 'views_count']);
        });
    }
};
