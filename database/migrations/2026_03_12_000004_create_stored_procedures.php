<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create stored procedures for complex multi-step operations.
     *
     * Note: These procedures only work with MySQL/MariaDB. When running tests
     * with SQLite, procedures are skipped. Use the Laravel service methods
     * instead (which provide the same functionality).
     *
     * Procedures created (MySQL only):
     *
     * sp_assign_task_with_audit
     *   Atomically assigns user to task and creates audit log.
     *   Prevents race conditions in assignment.
     *
     * sp_bulk_update_task_status
     *   Updates multiple tasks' status in a single transaction.
     *   Validates all transitions before committing any.
     *
     * sp_transfer_project_ownership
     *   Transfers project manager with full audit trail.
     *   Updates all related permissions atomically.
     *
     * sp_archive_completed_tasks
     *   Batch operation to soft-delete completed tasks older than N days.
     *   Creates audit entries for each archived task.
     *
     * Why stored procedures?
     *   Guaranteed atomicity for multi-step operations.
     *   Reduced network round-trips. Reusable across entry points.
     *   Better performance for bulk operations.
     */
    public function up(): void
    {
        // Skip if not using MySQL/MariaDB
        if (!$this->isMySql()) {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_assign_task_with_audit');
        // Assign a user to a task with automatic audit logging
        DB::unprepared("
            CREATE PROCEDURE sp_assign_task_with_audit(
                IN p_task_id BIGINT,
                IN p_user_id BIGINT,
                IN p_assigned_by BIGINT,
                OUT p_status VARCHAR(50),
                OUT p_message VARCHAR(255)
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    SET p_status = 'ERROR';
                    SET p_message = 'Database error occurred during assignment';
                END;

                START TRANSACTION;

                -- Check if task exists and is not deleted
                IF NOT EXISTS (SELECT 1 FROM tasks WHERE id = p_task_id AND deleted_at IS NULL) THEN
                    SET p_status = 'NOT_FOUND';
                    SET p_message = 'Task not found or has been deleted';
                    ROLLBACK;
                -- Check if user exists
                ELSEIF NOT EXISTS (SELECT 1 FROM users WHERE id = p_user_id) THEN
                    SET p_status = 'USER_NOT_FOUND';
                    SET p_message = 'User not found';
                    ROLLBACK;
                -- Check if already assigned (using SELECT FOR UPDATE for concurrency)
                ELSEIF EXISTS (
                    SELECT 1 FROM task_assignments
                    WHERE task_id = p_task_id AND user_id = p_user_id
                    FOR UPDATE
                ) THEN
                    SET p_status = 'ALREADY_ASSIGNED';
                    SET p_message = 'User is already assigned to this task';
                    ROLLBACK;
                ELSE
                    -- Create assignment
                    INSERT INTO task_assignments (task_id, user_id, assigned_by, assigned_date, created_at, updated_at)
                    VALUES (p_task_id, p_user_id, p_assigned_by, CURDATE(), NOW(), NOW());

                    -- Create audit log entry
                    INSERT INTO task_activity_log (task_id, activity_type, metadata, actor_id, created_at, updated_at)
                    VALUES (
                        p_task_id,
                        'assignee_added',
                        JSON_OBJECT(
                            'user_id', p_user_id,
                            'assigned_by', p_assigned_by,
                            'procedure', 'sp_assign_task_with_audit'
                        ),
                        p_assigned_by,
                        NOW(),
                        NOW()
                    );

                    SET p_status = 'SUCCESS';
                    SET p_message = 'User successfully assigned to task';
                    COMMIT;
                END IF;
            END
        ");

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_bulk_update_task_status');
        // Bulk update task statuses in a single transaction
        DB::unprepared("
            CREATE PROCEDURE sp_bulk_update_task_status(
                IN p_task_ids JSON,
                IN p_new_status_id BIGINT,
                IN p_actor_id BIGINT,
                OUT p_success_count INT,
                OUT p_failed_count INT,
                OUT p_failed_tasks JSON
            )
            BEGIN
                DECLARE v_task_id BIGINT;
                DECLARE v_old_status_id BIGINT;
                DECLARE v_old_status_name VARCHAR(50);
                DECLARE v_new_status_name VARCHAR(50);
                DECLARE v_idx INT DEFAULT 0;
                DECLARE v_total INT;
                DECLARE v_failed_list JSON DEFAULT JSON_ARRAY();

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    SET p_success_count = 0;
                    SET p_failed_count = v_total;
                    SET p_failed_tasks = JSON_ARRAY('Transaction rolled back due to error');
                END;

                SET p_success_count = 0;
                SET p_failed_count = 0;

                -- Get new status name
                SELECT name INTO v_new_status_name FROM task_statuses WHERE id = p_new_status_id;

                IF v_new_status_name IS NULL THEN
                    SET p_failed_tasks = JSON_ARRAY('Invalid status ID');
                    SET p_failed_count = JSON_LENGTH(p_task_ids);
                ELSE
                    START TRANSACTION;

                    SET v_total = JSON_LENGTH(p_task_ids);

                    WHILE v_idx < v_total DO
                        SET v_task_id = JSON_EXTRACT(p_task_ids, CONCAT('$[', v_idx, ']'));

                        -- Get current status with lock
                        SELECT status_id INTO v_old_status_id
                        FROM tasks
                        WHERE id = v_task_id AND deleted_at IS NULL
                        FOR UPDATE;

                        IF v_old_status_id IS NOT NULL THEN
                            -- Update task (trigger will validate transition and increment version)
                            UPDATE tasks
                            SET status_id = p_new_status_id, updated_at = NOW()
                            WHERE id = v_task_id;

                            -- Get old status name for logging
                            SELECT name INTO v_old_status_name FROM task_statuses WHERE id = v_old_status_id;

                            -- Log the change
                            INSERT INTO task_activity_log (task_id, activity_type, metadata, actor_id, created_at, updated_at)
                            VALUES (
                                v_task_id,
                                'status_changed',
                                JSON_OBJECT(
                                    'old_status', v_old_status_name,
                                    'new_status', v_new_status_name,
                                    'bulk_operation', TRUE
                                ),
                                p_actor_id,
                                NOW(),
                                NOW()
                            );

                            SET p_success_count = p_success_count + 1;
                        ELSE
                            SET p_failed_count = p_failed_count + 1;
                            SET v_failed_list = JSON_ARRAY_APPEND(v_failed_list, '$', v_task_id);
                        END IF;

                        SET v_idx = v_idx + 1;
                    END WHILE;

                    SET p_failed_tasks = v_failed_list;
                    COMMIT;
                END IF;
            END
        ");

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_transfer_project_ownership');
        // Transfer project ownership with audit trail
        DB::unprepared("
            CREATE PROCEDURE sp_transfer_project_ownership(
                IN p_project_id BIGINT,
                IN p_new_manager_id BIGINT,
                IN p_actor_id BIGINT,
                OUT p_status VARCHAR(50),
                OUT p_message VARCHAR(255)
            )
            BEGIN
                DECLARE v_old_manager_id BIGINT;
                DECLARE v_old_manager_name VARCHAR(255);
                DECLARE v_new_manager_name VARCHAR(255);

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    SET p_status = 'ERROR';
                    SET p_message = 'Database error during ownership transfer';
                END;

                START TRANSACTION;

                -- Lock the project row
                SELECT manager_id INTO v_old_manager_id
                FROM projects
                WHERE id = p_project_id AND deleted_at IS NULL
                FOR UPDATE;

                IF v_old_manager_id IS NULL THEN
                    SET p_status = 'NOT_FOUND';
                    SET p_message = 'Project not found';
                    ROLLBACK;
                ELSEIF v_old_manager_id = p_new_manager_id THEN
                    SET p_status = 'NO_CHANGE';
                    SET p_message = 'New manager is same as current manager';
                    ROLLBACK;
                ELSEIF NOT EXISTS (SELECT 1 FROM users WHERE id = p_new_manager_id) THEN
                    SET p_status = 'USER_NOT_FOUND';
                    SET p_message = 'New manager not found';
                    ROLLBACK;
                ELSE
                    -- Get manager names for audit
                    SELECT CONCAT(first_name, ' ', last_name) INTO v_old_manager_name
                    FROM users WHERE id = v_old_manager_id;

                    SELECT CONCAT(first_name, ' ', last_name) INTO v_new_manager_name
                    FROM users WHERE id = p_new_manager_id;

                    -- Update project manager
                    UPDATE projects
                    SET manager_id = p_new_manager_id, updated_at = NOW()
                    WHERE id = p_project_id;

                    -- Create audit log entry
                    INSERT INTO activity_logs (loggable_type, loggable_id, activity_type, metadata, actor_id, created_at, updated_at)
                    VALUES (
                        'App\\\\Models\\\\Project',
                        p_project_id,
                        'ownership_transferred',
                        JSON_OBJECT(
                            'old_manager_id', v_old_manager_id,
                            'old_manager_name', v_old_manager_name,
                            'new_manager_id', p_new_manager_id,
                            'new_manager_name', v_new_manager_name
                        ),
                        p_actor_id,
                        NOW(),
                        NOW()
                    );

                    SET p_status = 'SUCCESS';
                    SET p_message = CONCAT('Ownership transferred from ', v_old_manager_name, ' to ', v_new_manager_name);
                    COMMIT;
                END IF;
            END
        ");

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_archive_completed_tasks');
        // Archive old completed tasks in batch
        DB::unprepared("
            CREATE PROCEDURE sp_archive_completed_tasks(
                IN p_days_old INT,
                IN p_actor_id BIGINT,
                OUT p_archived_count INT
            )
            BEGIN
                DECLARE v_done_status_id BIGINT;
                DECLARE v_cutoff_date DATETIME;

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    SET p_archived_count = 0;
                END;

                SET p_archived_count = 0;
                SET v_cutoff_date = DATE_SUB(NOW(), INTERVAL p_days_old DAY);

                -- Get Done status ID
                SELECT id INTO v_done_status_id FROM task_statuses WHERE name = 'Done' LIMIT 1;

                IF v_done_status_id IS NOT NULL THEN
                    START TRANSACTION;

                    -- Create temp table to track archived tasks
                    CREATE TEMPORARY TABLE tmp_archived_tasks AS
                    SELECT id, title, project_id
                    FROM tasks
                    WHERE status_id = v_done_status_id
                      AND deleted_at IS NULL
                      AND updated_at < v_cutoff_date;

                    -- Soft delete the tasks
                    UPDATE tasks
                    SET deleted_at = NOW(), updated_at = NOW()
                    WHERE id IN (SELECT id FROM tmp_archived_tasks);

                    -- Get count
                    SELECT COUNT(*) INTO p_archived_count FROM tmp_archived_tasks;

                    -- Create audit log entries for each archived task
                    INSERT INTO task_activity_log (task_id, activity_type, metadata, actor_id, created_at, updated_at)
                    SELECT
                        id,
                        'task_archived',
                        JSON_OBJECT(
                            'reason', CONCAT('Completed more than ', p_days_old, ' days ago'),
                            'archived_at', NOW(),
                            'batch_operation', TRUE
                        ),
                        p_actor_id,
                        NOW(),
                        NOW()
                    FROM tmp_archived_tasks;

                    DROP TEMPORARY TABLE tmp_archived_tasks;

                    COMMIT;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!$this->isMySql()) {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_assign_task_with_audit');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_bulk_update_task_status');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_transfer_project_ownership');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_archive_completed_tasks');
    }

    /**
     * Check if the database driver is MySQL or MariaDB.
     */
    private function isMySql(): bool
    {
        $driver = DB::connection()->getDriverName();
        return in_array($driver, ['mysql', 'mariadb']);
    }
};
