<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_legacy')->default(false);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['CCS', 'College of Computer Studies', true, false],
            ['CBA', 'College of Business Administration', true, false],
            ['COE', 'College of Engineering', true, false],
            ['COED', 'College of Education', true, false],
            ['CCJ', 'College of Criminal Justice', true, false],
            ['CAD', 'College of Arts and Design (Legacy)', false, true],
        ] as [$code, $name, $active, $legacy]) {
            DB::table('departments')->insert([
                'code' => $code,
                'name' => $name,
                'is_active' => $active,
                'is_legacy' => $legacy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->where('department', 'BSCRIM')->update(['department' => 'CCJ']);
        DB::table('users')->where('department', 'CED')->update(['department' => 'COED']);

        foreach (DB::table('users')->whereNotNull('department')->distinct()->pluck('department') as $code) {
            if (! DB::table('departments')->where('code', $code)->exists()) {
                DB::table('departments')->insert([
                    'code' => $code,
                    'name' => "{$code} (Legacy department)",
                    'is_active' => false,
                    'is_legacy' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department')->references('code')->on('departments')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('organizers', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
        });

        DB::table('organizers')
            ->join('users', 'users.id', '=', 'organizers.user_id')
            ->whereNotNull('users.department')
            ->select(['organizers.id', 'users.department'])
            ->get()
            ->each(function (object $organizer): void {
                $departmentId = DB::table('departments')->where('code', $organizer->department)->value('id');
                if ($departmentId) {
                    DB::table('organizers')->where('id', $organizer->id)->update(['department_id' => $departmentId]);
                }
            });

        Schema::create('organizer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('membership_role', 30)->default('officer');
            $table->timestamps();
            $table->unique(['organizer_id', 'user_id']);
        });

        DB::table('organizers')->whereNotNull('user_id')->get(['id', 'user_id'])->each(function (object $organizer) use ($now): void {
            DB::table('organizer_user')->insertOrIgnore([
                'organizer_id' => $organizer->id,
                'user_id' => $organizer->user_id,
                'membership_role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->index();
            $table->softDeletes();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->softDeletes();
            $table->foreignId('organizer_id')->nullable()->change();
        });

        Schema::table('event_reports', function (Blueprint $table) {
            $table->text('resolution')->nullable();
        });

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('record_type', 100)->index();
            $table->unsignedBigInteger('record_id')->nullable()->index();
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('user_invitations');

        Schema::table('event_reports', function (Blueprint $table) {
            $table->dropColumn('resolution');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropSoftDeletes();
            $table->foreignId('organizer_id')->nullable(false)->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_published');
            $table->dropSoftDeletes();
        });

        Schema::dropIfExists('organizer_user');

        Schema::table('organizers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department']);
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('departments');
    }
};
