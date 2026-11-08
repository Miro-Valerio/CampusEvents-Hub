<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_cancelled')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_url')->nullable();
            $table->json('notification_preferences')->nullable();
        });

        Schema::create('event_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 50);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reports');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_url', 'notification_preferences']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_cancelled', 'views_count']);
        });
    }
};
