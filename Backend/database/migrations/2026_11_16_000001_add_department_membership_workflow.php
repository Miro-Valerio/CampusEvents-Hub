<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('organizer_user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
        });

        foreach (DB::table('departments')->orderBy('id')->get(['id', 'code']) as $department) {
            $departmentProfile = DB::table('organizers')
                ->where('department_id', $department->id)
                ->orderBy('id')
                ->first(['description']);
            if ($departmentProfile?->description) {
                DB::table('departments')
                    ->where('id', $department->id)
                    ->whereNull('description')
                    ->update(['description' => $departmentProfile->description]);
            }

            $organizerUserId = DB::table('organizers')
                ->join('users', 'users.id', '=', 'organizers.user_id')
                ->where('organizers.department_id', $department->id)
                ->where('users.role', 'organizer')
                ->where('users.is_active', true)
                ->orderBy('organizers.id')
                ->value('users.id');

            if ($organizerUserId && ! DB::table('departments')->where('organizer_user_id', $organizerUserId)->exists()) {
                DB::table('departments')->where('id', $department->id)->update([
                    'organizer_user_id' => $organizerUserId,
                ]);
            }
        }

        Schema::create('department_membership_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_membership_requests');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_user_id');
        });
    }
};
