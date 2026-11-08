<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;

class AdminActivityLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        User $actor,
        string $action,
        string $recordType,
        ?int $recordId,
        string $summary,
        array $details = [],
    ): void {
        AdminActivityLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'summary' => $summary,
            'details' => $details ?: null,
        ]);
    }
}
