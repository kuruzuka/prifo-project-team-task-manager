<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add version columns for optimistic locking.
     *
     * These columns enable detection of concurrent modification conflicts.
     * When updating a record, we check that the version hasn't changed since
     * we last read it. If it has, another user modified it, and we reject
     * the update with a conflict error.
     *
     * Applied to:
     * - tasks: High-frequency updates (status, priority, progress, assignments)
     * - projects: Moderate updates but critical for multi-team coordination
     *
     * Not applied to:
     * - comments: Immutable after creation (only edited by author)
     * - activity_logs: Write-only audit records
     * - lookup tables: Rarely updated, admin-only
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(1)->after('created_by');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(1)->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
