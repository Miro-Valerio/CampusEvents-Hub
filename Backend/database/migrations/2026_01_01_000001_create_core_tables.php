<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('departments', function(Blueprint $t){
            $t->id(); $t->string('name'); $t->string('code',20)->unique();
            $t->text('description')->nullable(); $t->string('email')->nullable(); $t->timestamps();
        });
        Schema::create('users', function(Blueprint $t){
            $t->id(); $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name'); $t->string('email')->unique(); $t->string('course')->nullable();
            $t->string('role',24)->default('student')->index(); $t->json('officer_permissions')->nullable();
            $t->json('preferences')->nullable(); $t->string('photo_path')->nullable();
            $t->timestamp('email_verified_at')->nullable(); $t->string('password'); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('password_reset_tokens', function(Blueprint $t){
            $t->string('email')->primary(); $t->string('token'); $t->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function(Blueprint $t){
            $t->string('id')->primary(); $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address',45)->nullable(); $t->text('user_agent')->nullable();
            $t->longText('payload'); $t->integer('last_activity')->index();
        });
        Schema::create('personal_access_tokens', function(Blueprint $t){
            $t->id(); $t->morphs('tokenable'); $t->string('name'); $t->string('token',64)->unique();
            $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index(); $t->timestamps();
        });
        Schema::create('events', function(Blueprint $t){
            $t->id(); $t->foreignId('department_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $t->string('title'); $t->text('description'); $t->string('venue');
            $t->dateTime('starts_at')->index(); $t->dateTime('ends_at');
            $t->string('category',60)->default('Campus'); $t->unsignedInteger('seat_limit')->default(50);
            $t->string('poster_path')->nullable(); $t->text('rules')->nullable();
            $t->string('status',20)->default('published')->index();
            $t->string('check_in_code',12)->nullable(); $t->unsignedBigInteger('views')->default(0);
            $t->timestamps();
        });
        Schema::create('rsvps', function(Blueprint $t){
            $t->id(); $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('seat_no')->nullable(); $t->string('status',20)->default('active');
            $t->timestamp('reminder_sent_at')->nullable(); $t->timestamps();
            $t->unique(['event_id','user_id']); $t->unique(['event_id','seat_no']); $t->index(['event_id','status']);
        });
        Schema::create('bookmarks', function(Blueprint $t){
            $t->id(); $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->timestamps();
            $t->unique(['event_id','user_id']);
        });
        Schema::create('calendar_entries', function(Blueprint $t){
            $t->id(); $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->timestamps();
            $t->unique(['event_id','user_id']);
        });
        Schema::create('event_check_ins', function(Blueprint $t){
            $t->id(); $t->foreignId('rsvp_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('checked_at'); $t->timestamps();
        });
        Schema::create('announcements', function(Blueprint $t){
            $t->id(); $t->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $t->string('title'); $t->text('body'); $t->string('target_course')->nullable();
            $t->boolean('is_pinned')->default(false); $t->timestamps();
        });
        Schema::create('announcement_reads', function(Blueprint $t){
            $t->id(); $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->timestamp('read_at');
            $t->unique(['announcement_id','user_id']);
        });
        Schema::create('announcement_reactions', function(Blueprint $t){
            $t->id(); $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('emoji',10);
            $t->timestamps(); $t->unique(['announcement_id','user_id']);
        });
        Schema::create('user_notifications', function(Blueprint $t){
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type',50); $t->string('title'); $t->text('message');
            $t->string('link')->nullable(); $t->timestamp('read_at')->nullable(); $t->timestamps();
            $t->index(['user_id','read_at']);
        });
        Schema::create('memberships', function(Blueprint $t){
            $t->id(); $t->foreignId('department_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status',24)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->unique(['department_id','user_id']);
        });
        Schema::create('event_reports', function(Blueprint $t){
            $t->id(); $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('reason'); $t->string('status',20)->default('pending');
            $t->text('resolution')->nullable(); $t->timestamps();
        });
        Schema::create('audit_logs', function(Blueprint $t){
            $t->id(); $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action'); $t->string('subject_type',80)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable(); $t->json('details')->nullable(); $t->timestamps();
        });
        Schema::create('system_settings', function(Blueprint $t){
            $t->string('key')->primary(); $t->text('value')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        foreach(['system_settings','audit_logs','event_reports','memberships','user_notifications','announcement_reactions','announcement_reads','announcements','event_check_ins','calendar_entries','bookmarks','rsvps','events','personal_access_tokens','sessions','password_reset_tokens','users','departments'] as $name) Schema::dropIfExists($name);
    }
};
