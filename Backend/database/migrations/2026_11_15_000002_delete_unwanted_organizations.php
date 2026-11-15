<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $reassignments = [
                'IT Student Organization' => 'College of Computer Studies (CCS)',
                'Arts & Culture Guild' => 'SPC Student Affairs',
            ];

            foreach ($reassignments as $organizationName => $replacementName) {
                $organizationId = DB::table('organizers')->where('name', $organizationName)->value('id');
                if (! $organizationId) {
                    continue;
                }

                $hasHistory = DB::table('events')->where('organizer_id', $organizationId)->exists()
                    || DB::table('announcements')->where('organizer_id', $organizationId)->exists();
                if ($hasHistory) {
                    $replacementId = DB::table('organizers')->where('name', $replacementName)->value('id');
                    if (! $replacementId) {
                        throw new RuntimeException("Cannot preserve {$organizationName} history without {$replacementName}.");
                    }

                    DB::table('events')
                        ->where('organizer_id', $organizationId)
                        ->update(['organizer_id' => $replacementId]);
                    DB::table('announcements')
                        ->where('organizer_id', $organizationId)
                        ->update(['organizer_id' => $replacementId]);
                }

                DB::table('organizers')->where('id', $organizationId)->delete();
            }

            DB::table('users')
                ->where('email', 'it.organizer@spc.edu.ph')
                ->where('role', 'organizer')
                ->update(['role' => 'student']);
        });
    }

    public function down(): void
    {
        $now = now();
        foreach ([
            'IT Student Organization' => 'Technology talks, workshops, and student-led events.',
            'Arts & Culture Guild' => 'Music, theater, and visual arts showcases by SPC students.',
        ] as $name => $description) {
            if (DB::table('organizers')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('organizers')->insert([
                'name' => $name,
                'description' => $description,
                'is_active' => false,
                'is_listed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')
            ->where('email', 'it.organizer@spc.edu.ph')
            ->where('role', 'student')
            ->update(['role' => 'organizer']);
    }
};
