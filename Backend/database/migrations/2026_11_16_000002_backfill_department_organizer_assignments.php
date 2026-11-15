<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('departments')->whereNull('organizer_user_id')->orderBy('id')->get(['id', 'code']) as $department) {
            $candidates = DB::table('users')
                ->join('organizers', 'organizers.user_id', '=', 'users.id')
                ->where('users.department', $department->code)
                ->where('users.role', 'organizer')
                ->where('users.is_active', true)
                ->where('organizers.is_active', true)
                ->where(function ($query) use ($department): void {
                    $query->whereNull('organizers.department_id')
                        ->orWhere('organizers.department_id', $department->id);
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('departments as assigned_departments')
                        ->whereColumn('assigned_departments.organizer_user_id', 'users.id');
                })
                ->orderBy('organizers.id')
                ->get(['users.id as user_id', 'organizers.id as organizer_id']);

            if ($candidates->count() !== 1) {
                continue;
            }

            $candidate = $candidates->first();
            DB::table('organizers')
                ->where('id', $candidate->organizer_id)
                ->whereNull('department_id')
                ->update(['department_id' => $department->id]);
            DB::table('departments')
                ->where('id', $department->id)
                ->whereNull('organizer_user_id')
                ->update(['organizer_user_id' => $candidate->user_id]);
        }
    }

    public function down(): void
    {
    }
};
