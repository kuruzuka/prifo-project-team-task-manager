<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes for locking and audit queries.
     *
     * These indexes optimize:
     * - Version lookups for optimistic locking
     * - Audit trail queries by entity and time
     * - Task activity log queries
     * - Concurrent access patterns
     */
    public function up(): void
    {
        // Optimize task_activity_log queries (was missing composite index)
        Schema::table('task_activity_log', function (Blueprint $table) {
            $table->index(['task_id', 'created_at'], 'idx_task_activity_timeline');
            $table->index(['actor_id', 'created_at'], 'idx_actor_activity_timeline');
            $table->index(['activity_type', 'created_at'], 'idx_activity_type_timeline');
        });

        // Optimize task lookups for concurrent updates
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['id', 'version'], 'idx_task_version_lock');
            $table->index(['project_id', 'status_id', 'deleted_at'], 'idx_task_project_status');
        });

        // Optimize project lookups for concurrent updates
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['id', 'version'], 'idx_project_version_lock');
        });

        // Optimize assignment lookups for concurrent assignment checks
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->index(['task_id', 'user_id', 'assigned_date'], 'idx_assignment_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_activity_log', function (Blueprint $table) {
            $table->dropIndex('idx_task_activity_timeline');
            $table->dropIndex('idx_actor_activity_timeline');
            $table->dropIndex('idx_activity_type_timeline');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_task_version_lock');
            $table->dropIndex('idx_task_project_status');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('idx_project_version_lock');
        });

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_assignment_lookup');
        });
    }
};
