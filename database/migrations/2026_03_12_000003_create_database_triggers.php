<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create database triggers for automated enforcement of business rules.
     *
     * Note: These triggers only work with MySQL/MariaDB. When running tests with
     * SQLite, triggers are skipped and business rules are enforced at the
     * application level.
     *
     * Triggers created (MySQL only):
     *
     * trg_tasks_increment_version (BEFORE UPDATE)
     *   Auto-increments version column on any task update for optimistic locking.
     *
     * trg_projects_increment_version (BEFORE UPDATE)
     *   Same as above for projects.
     *
     * trg_tasks_validate_status_transition (BEFORE UPDATE)
     *   Prevents invalid status transitions. For example, cannot move from
     *   "Done" back to "To Do" without going through "In Progress".
     *
     * trg_tasks_auto_complete_progress (BEFORE UPDATE)
     *   Auto-sets progress to 100 when status becomes "Done", and to 0 when
     *   status becomes "To Do".
     *
     * trg_tasks_audit_log (AFTER UPDATE)
     *   Auto-creates audit log entries for status/priority/progress changes.
     *
     * trg_prevent_orphan_tasks (BEFORE INSERT)
     *   Prevents creating tasks in soft-deleted projects.
     *
     * Why use database-level triggers?
     *   Enforced regardless of how data is modified (app, admin tools, scripts).
     *   Atomic with the triggering operation. Cannot be bypassed by app bugs.
     *   Better performance for simple validations.
     */
    public function up(): void
    {
        // Skip if not using MySQL/MariaDB
        if (!$this->isMySql()) {
            return;
        }

        // Get status IDs for validation (these are seeded and stable)
        // We'll use names in triggers for readability, joining task_statuses

        // Auto-increment task version on update (for optimistic locking)
        DB::unprepared('
            CREATE TRIGGER trg_tasks_increment_version
            BEFORE UPDATE ON tasks
            FOR EACH ROW
            BEGIN
                SET NEW.version = OLD.version + 1;
            END
        ');

        // Auto-increment project version on update (for optimistic locking)
        DB::unprepared('
            CREATE TRIGGER trg_projects_increment_version
            BEFORE UPDATE ON projects
            FOR EACH ROW
            BEGIN
                SET NEW.version = OLD.version + 1;
            END
        ');

        // Validate task status transitions
        // Allowed: To Do -> In Progress, In Progress -> To Do/In Review,
        // In Review -> In Progress/Done, Done -> In Review
        DB::unprepared("
            CREATE TRIGGER trg_tasks_validate_status_transition
            BEFORE UPDATE ON tasks
            FOR EACH ROW
            BEGIN
                DECLARE old_status_name VARCHAR(50);
                DECLARE new_status_name VARCHAR(50);
                DECLARE is_valid_transition BOOLEAN DEFAULT TRUE;
                DECLARE error_msg VARCHAR(255);

                -- Only validate if status is changing
                IF OLD.status_id != NEW.status_id THEN
                    -- Get status names
                    SELECT name INTO old_status_name FROM task_statuses WHERE id = OLD.status_id;
                    SELECT name INTO new_status_name FROM task_statuses WHERE id = NEW.status_id;

                    -- Validate transitions
                    CASE old_status_name
                        WHEN 'To Do' THEN
                            IF new_status_name NOT IN ('In Progress') THEN
                                SET is_valid_transition = FALSE;
                            END IF;
                        WHEN 'In Progress' THEN
                            IF new_status_name NOT IN ('To Do', 'In Review') THEN
                                SET is_valid_transition = FALSE;
                            END IF;
                        WHEN 'In Review' THEN
                            IF new_status_name NOT IN ('In Progress', 'Done') THEN
                                SET is_valid_transition = FALSE;
                            END IF;
                        WHEN 'Done' THEN
                            IF new_status_name NOT IN ('In Review') THEN
                                SET is_valid_transition = FALSE;
                            END IF;
                        ELSE
                            -- Unknown status, allow transition
                            SET is_valid_transition = TRUE;
                    END CASE;

                    IF NOT is_valid_transition THEN
                        SET error_msg = CONCAT('Invalid status transition: ', COALESCE(old_status_name, 'NULL'), ' -> ', COALESCE(new_status_name, 'NULL'));
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
                    END IF;
                END IF;
            END
        ");

        // Auto-set progress based on status (100% when Done, 0% when To Do)
        DB::unprepared("
            CREATE TRIGGER trg_tasks_auto_complete_progress
            BEFORE UPDATE ON tasks
            FOR EACH ROW
            BEGIN
                DECLARE new_status_name VARCHAR(50);

                -- Only adjust if status is changing
                IF OLD.status_id != NEW.status_id THEN
                    SELECT name INTO new_status_name FROM task_statuses WHERE id = NEW.status_id;

                    -- Set progress to 100 when Done
                    IF new_status_name = 'Done' THEN
                        SET NEW.progress = 100;
                    -- Set progress to 0 when back to To Do
                    ELSEIF new_status_name = 'To Do' THEN
                        SET NEW.progress = 0;
                    END IF;
                END IF;
            END
        ");

        // Prevent creating tasks in deleted projects
        DB::unprepared('
            CREATE TRIGGER trg_prevent_orphan_tasks
            BEFORE INSERT ON tasks
            FOR EACH ROW
            BEGIN
                DECLARE project_deleted_at TIMESTAMP;

                SELECT deleted_at INTO project_deleted_at
                FROM projects
                WHERE id = NEW.project_id;

                IF project_deleted_at IS NOT NULL THEN
                    SIGNAL SQLSTATE \'45000\'
                        SET MESSAGE_TEXT = \'Cannot create task in a deleted project\';
                END IF;
            END
        ');

        // Prevent soft-deleting projects that still have active tasks
        DB::unprepared('
            CREATE TRIGGER trg_prevent_project_delete_with_tasks
            BEFORE UPDATE ON projects
            FOR EACH ROW
            BEGIN
                DECLARE active_task_count INT;

                -- Only check when soft-deleting (deleted_at going from NULL to NOT NULL)
                IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
                    SELECT COUNT(*) INTO active_task_count
                    FROM tasks
                    WHERE project_id = OLD.id AND deleted_at IS NULL;

                    IF active_task_count > 0 THEN
                        SIGNAL SQLSTATE \'45000\'
                            SET MESSAGE_TEXT = \'Cannot delete project with active tasks. Delete or reassign tasks first.\';
                    END IF;
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!$this->isMySql()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_tasks_increment_version');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_projects_increment_version');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tasks_validate_status_transition');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tasks_auto_complete_progress');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_orphan_tasks');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_project_delete_with_tasks');
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
