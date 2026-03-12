<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the transaction_logs table for comprehensive audit trail.
     *
     * This table captures transaction-level metadata for all critical database
     * operations. Unlike activity_logs (business events) and task_activity_log
     * (task-specific events), this table records:
     *
     * - Transaction boundaries (commit/rollback)
     * - Multi-table operations as single entries
     * - Performance metrics (duration_ms)
     * - Error details on failures
     *
     * Retention: Configure via scheduled job to archive/delete old entries.
     */
    public function up(): void
    {
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();

            // Transaction identification
            $table->uuid('transaction_id')->comment('Groups related operations');
            $table->string('operation_type', 50)->comment('create, update, delete, batch, etc.');
            $table->string('operation_name', 100)->comment('Human-readable action name');

            // Affected entities (polymorphic, nullable for batch operations)
            $table->string('entity_type', 100)->nullable()->comment('Model class name');
            $table->unsignedBigInteger('entity_id')->nullable()->comment('Primary key of entity');

            // Actor tracking
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_ip', 45)->nullable();
            $table->string('actor_user_agent', 500)->nullable();

            // Change data
            $table->json('old_values')->nullable()->comment('State before change');
            $table->json('new_values')->nullable()->comment('State after change');
            $table->json('context')->nullable()->comment('Additional context (route, params)');

            // Transaction outcome
            $table->enum('status', ['started', 'committed', 'rolled_back', 'failed'])
                ->default('started');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Timestamps with high precision for ordering
            $table->timestamp('started_at', 6)->useCurrent();
            $table->timestamp('completed_at', 6)->nullable();

            // Indexes for efficient querying
            $table->index(['entity_type', 'entity_id', 'started_at'], 'idx_entity_lookup');
            $table->index(['actor_id', 'started_at'], 'idx_actor_audit');
            $table->index(['transaction_id'], 'idx_transaction_group');
            $table->index(['operation_type', 'started_at'], 'idx_operation_type');
            $table->index(['status', 'started_at'], 'idx_status_monitoring');
            $table->index(['started_at'], 'idx_time_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
    }
};
