<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reports', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('review_notes');
        });
    }

    public function down(): void
    {
        Schema::table('event_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_notes', 'reviewed_at']);
        });
    }
};
