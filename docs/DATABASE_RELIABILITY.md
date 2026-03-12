# Database Reliability & Concurrency Control

> **Version:** 1.0.0  
> **Last Updated:** March 12, 2026  
> **For:** Junior Developers & System Maintainers

This document explains the database reliability features implemented in this project. It covers what each feature does, where to find the code, and how to maintain or extend these systems safely.

---

## Table of Contents

1. [Overview](#overview)
2. [Quick Reference: Where Everything Lives](#quick-reference-where-everything-lives)
3. [Core Concepts Explained](#core-concepts-explained)
   - [Database Triggers](#database-triggers)
   - [Stored Procedures](#stored-procedures)
   - [Transaction Logging](#transaction-logging)
   - [Optimistic Locking](#optimistic-locking)
   - [Pessimistic Locking](#pessimistic-locking)
   - [Concurrency Control](#concurrency-control)
4. [Detailed Implementation Guide](#detailed-implementation-guide)
5. [Code Walkthroughs](#code-walkthroughs)
6. [How These Features Protect the System](#how-these-features-protect-the-system)
7. [Maintenance & Extension Guide](#maintenance--extension-guide)
8. [Troubleshooting](#troubleshooting)
9. [Performance Considerations](#performance-considerations)

---

## Overview

This system implements **production-grade reliability features** similar to tools like Jira, Asana, and Linear. The key principles are:

1. **Data Integrity** — Data is never left in an inconsistent state
2. **Conflict Prevention** — Multiple users can work safely without overwriting each other
3. **Audit Trail** — Every important change is recorded for investigation
4. **Automated Enforcement** — Business rules are enforced at the database level

### What Was NOT Applied Globally (and Why)

| Feature | Applied To | Why Not Everywhere |
|---------|------------|-------------------|
| Optimistic Locking | Tasks, Projects | Lookup tables (statuses, roles) change rarely and don't need conflict detection |
| Transaction Logging | Critical operations | Read operations and simple queries don't need logging (would bloat the database) |
| Pessimistic Locking | Assignment operations | Only high-conflict operations need exclusive locks; overuse causes performance issues |
| Database Triggers | Tasks, Projects | Lookup tables and audit tables don't need automated validations |

---

## Quick Reference: Where Everything Lives

### Database Components

| Component | Location | Purpose |
|-----------|----------|---------|
| Version columns | `migrations/2026_03_12_000001_add_version_columns_for_optimistic_locking.php` | Enable optimistic locking |
| Transaction logs table | `migrations/2026_03_12_000002_create_transaction_logs_table.php` | Store audit trail |
| Database triggers | `migrations/2026_03_12_000003_create_database_triggers.php` | Auto-enforce rules |
| Stored procedures | `migrations/2026_03_12_000004_create_stored_procedures.php` | Complex atomic operations |
| Performance indexes | `migrations/2026_03_12_000005_add_performance_indexes.php` | Query optimization |

### Application Components

| Component | Location | Purpose |
|-----------|----------|---------|
| TransactionLog model | `app/Models/TransactionLog.php` | Interact with audit logs |
| HasOptimisticLocking trait | `app/Concerns/HasOptimisticLocking.php` | Add version checking to models |
| UsesConcurrencyControl trait | `app/Concerns/UsesConcurrencyControl.php` | Add transaction helpers to controllers |
| TransactionManager service | `app/Services/TransactionManager.php` | Managed transactions with logging |
| LockManager service | `app/Services/LockManager.php` | Pessimistic locking helpers |
| StoredProcedureService | `app/Services/StoredProcedureService.php` | Call stored procedures from Laravel |
| StaleModelException | `app/Exceptions/StaleModelException.php` | Handle version conflicts |
| LockAcquisitionException | `app/Exceptions/LockAcquisitionException.php` | Handle lock timeouts |
| ArchiveCompletedTasks command | `app/Console/Commands/ArchiveCompletedTasks.php` | Batch archive old tasks |

### Modified Files

| File | Changes Made |
|------|--------------|
| `app/Models/Task.php` | Added `HasOptimisticLocking` trait, `version` field |
| `app/Models/Project.php` | Added `HasOptimisticLocking` trait, `version` field |
| `app/Http/Controllers/TaskController.php` | Added `UsesConcurrencyControl`, wrapped operations in transactions |
| `app/Http/Controllers/CommentController.php` | Added `UsesConcurrencyControl`, wrapped operations in transactions |

---

## Core Concepts Explained

### Database Triggers

**What they are:** Code that runs automatically in the database when data changes. Like event listeners, but at the database level.

**Why we use them:**
- They run regardless of how data is changed (app, admin tools, scripts)
- They're atomic with the triggering operation (all-or-nothing)
- They can't be bypassed by application bugs

**Our triggers:**

| Trigger | What It Does | When It Fires |
|---------|--------------|---------------|
| `trg_tasks_increment_version` | Auto-increments version number | Before any task update |
| `trg_projects_increment_version` | Auto-increments version number | Before any project update |
| `trg_tasks_validate_status_transition` | Prevents invalid status changes | Before status is changed |
| `trg_tasks_auto_complete_progress` | Sets progress to 100% when Done | Before status changes to Done |
| `trg_prevent_orphan_tasks` | Blocks tasks in deleted projects | Before task insert |
| `trg_prevent_project_delete_with_tasks` | Blocks deleting project with tasks | Before project soft-delete |

**Example - Status Transition Validation:**
```
Allowed: To Do → In Progress → In Review → Done
Blocked: To Do → Done (must go through review process)
```

---

### Stored Procedures

**What they are:** Pre-written SQL programs stored in the database. Like functions, but they run entirely in the database.

**Why we use them:**
- Reduced network round-trips (one call instead of many)
- Guaranteed atomicity for complex operations
- Reusable across different parts of the application

**Our procedures:**

| Procedure | What It Does | When To Use |
|-----------|--------------|-------------|
| `sp_assign_task_with_audit` | Assigns user + creates audit log atomically | When assigning users to tasks |
| `sp_bulk_update_task_status` | Updates many tasks' status in one transaction | Bulk status changes |
| `sp_transfer_project_ownership` | Changes project manager with full audit | Admin ownership transfers |
| `sp_archive_completed_tasks` | Batch-archives old completed tasks | Scheduled cleanup job |

**Calling from Laravel:**
```php
$result = $procedureService->assignTaskWithAudit($taskId, $userId, auth()->id());

if ($procedureService->isSuccess($result)) {
    // Assignment succeeded
} else {
    // Handle error: $result['message']
}
```

---

### Transaction Logging

**What it is:** Recording every important database change with who did it, when, and what changed.

**What we log:**
- Task creation, updates, deletion
- Assignment changes
- Status/priority/progress changes
- Project modifications
- System operations (batch archives)

**What we DON'T log (to avoid database bloat):**
- Read operations (viewing pages)
- Login/session activity (use Laravel's built-in logging)
- Comments (already logged in `task_activity_log`)
- Lookup table changes (rare admin operations)

**Transaction log structure:**
```
+-------------------+--------------------------------------------------+
| Field             | Purpose                                          |
+-------------------+--------------------------------------------------+
| transaction_id    | Groups related operations (UUID)                 |
| operation_type    | create, update, delete, batch, assign            |
| operation_name    | Human-readable description                       |
| entity_type       | Model class (App\Models\Task)                    |
| entity_id         | Primary key of affected record                   |
| actor_id          | User who performed the action                    |
| old_values        | State before the change (JSON)                   |
| new_values        | State after the change (JSON)                    |
| status            | started, committed, rolled_back, failed          |
| duration_ms       | How long the operation took                      |
+-------------------+--------------------------------------------------+
```

---

### Optimistic Locking

**What it is:** A way to detect when two users edit the same record at the same time. Instead of blocking access, it checks for conflicts when saving.

**How it works:**
1. User A loads a task (version = 5)
2. User B loads the same task (version = 5)
3. User B saves changes → version becomes 6
4. User A tries to save → "Expected version 5, found 6" → REJECTED

**When to use:**
- Most edit operations in web applications
- When conflicts are **rare** but possible
- When you don't want to block users from viewing data

**Code example:**
```php
// In controller
return $this->withVersionCheck(
    entity: $task,
    expectedVersion: $request->input('version'),
    callback: function () use ($task, $validated) {
        $task->update($validated);
        return back()->with('success', 'Updated!');
    }
);
```

**Frontend integration:**
The frontend should send the `version` field it received when loading the record:
```javascript
// When saving
axios.post('/tasks/1/status', {
    status_id: 3,
    version: task.version  // Send the version we loaded
});
```

---

### Pessimistic Locking

**What it is:** Blocking other users from modifying a record while you're working on it. Like putting a "Reserved" sign on the record.

**How it works:**
1. User A starts editing (acquires lock)
2. User B tries to edit → Must wait (or timeout error)
3. User A finishes and releases lock
4. User B can now proceed

**When to use:**
- High-conflict operations (task assignments)
- When conflicts are **common**
- Short-duration operations only (don't hold locks for long!)

**Code example:**
```php
// Locks the task row until the callback completes
return $this->withLockedTransaction(
    operationName: 'Add Assignee',
    entity: $task,
    callback: function ($lockedTask) {
        // $lockedTask is the locked version
        $lockedTask->assignedUsers()->attach($userId);
    }
);
```

**⚠️ Warning:** Never hold locks for more than a few seconds!

---

### Concurrency Control

**What it is:** Ensuring that simultaneous operations don't corrupt data. Combines transactions, locking, and isolation levels.

**Key principles:**

1. **Atomicity** — Operations either fully complete or fully roll back
2. **Isolation** — Concurrent transactions see consistent data
3. **Durability** — Committed changes survive system crashes

**What we protect against:**

| Problem | Description | Our Solution |
|---------|-------------|--------------|
| Lost Updates | User B overwrites User A's changes | Optimistic locking |
| Dirty Reads | Reading uncommitted changes | Transaction isolation |
| Race Conditions | Two users assign same person twice | Pessimistic locking + unique constraints |
| Partial Commits | Comment saved but audit log failed | Transactions |

---

## Detailed Implementation Guide

### Adding Optimistic Locking to a New Model

1. **Add migration for version column:**
```php
Schema::table('your_table', function (Blueprint $table) {
    $table->unsignedBigInteger('version')->default(1);
});
```

2. **Add trigger for auto-increment:**
```sql
CREATE TRIGGER trg_your_table_increment_version
BEFORE UPDATE ON your_table
FOR EACH ROW
BEGIN
    SET NEW.version = OLD.version + 1;
END
```

3. **Add trait to model:**
```php
class YourModel extends Model
{
    use HasOptimisticLocking;
    
    protected $fillable = ['field1', 'field2', 'version'];
    protected $hidden = ['version'];
}
```

4. **Use in controller:**
```php
return $this->withVersionCheck($model, $request->version, function () {
    // Update logic
});
```

---

### Wrapping Controller Actions in Transactions

**Basic transaction:**
```php
return $this->withTransaction(
    operationName: 'Create Task',
    entity: $task,
    callback: function () {
        // Your logic here
        return redirect()->back();
    },
    operationType: TransactionLog::TYPE_CREATE
);
```

**Transaction with locking:**
```php
return $this->withLockedTransaction(
    operationName: 'Assign User',
    entity: $task,
    callback: function ($lockedTask) {
        // $lockedTask is exclusively locked
    }
);
```

**Batch operation:**
```php
return $this->withBatchTransaction(
    operationName: 'Archive Tasks',
    operations: [
        fn () => $task1->archive(),
        fn () => $task2->archive(),
        fn () => $task3->archive(),
    ]
);
```

---

## Code Walkthroughs

### Task Status Update (with concurrency protection)

```php
// TaskController.php

public function updateStatus(Request $request, int $task): RedirectResponse
{
    // 1. Load the task (bypassing global scopes for admin access)
    $task = Task::withoutGlobalScopes()->findOrFail($task);
    
    // 2. Authorization check
    Gate::authorize('update', $task);
    
    // 3. Validate input (including optional version for conflict detection)
    $validated = $request->validate([
        'status_id' => ['required', 'integer', 'exists:task_statuses,id'],
        'version' => ['nullable', 'integer'],  // Optional: frontend sends current version
    ]);
    
    // 4. Execute with version check (from UsesConcurrencyControl trait)
    return $this->withVersionCheck(
        entity: $task,
        expectedVersion: $validated['version'] ?? null,
        callback: function () use ($task, $validated) {
            // 5. Capture old state for logging
            $oldStatus = $task->status?->name ?? 'None';
            
            // 6. Update (trigger validates transition + increments version)
            $task->update(['status_id' => $validated['status_id']]);
            
            // 7. Reload relationship for new status name
            $task->load('status:id,name');
            $newStatus = $task->status->name;
            
            // 8. Log activity
            $task->logTaskActivity('status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            
            return back()->with('success', "Status changed.");
        },
        operationName: 'Update Task Status'
    );
}
```

**What happens behind the scenes:**

1. `withVersionCheck()` starts a database transaction
2. Creates a `transaction_logs` entry with status "started"
3. Checks if version matches (if provided)
4. Executes your callback
5. `$task->update()` fires the `trg_tasks_validate_status_transition` trigger
6. Trigger validates: "In Progress → In Review" ✓ allowed
7. `trg_tasks_increment_version` trigger increments version
8. Activity log created
9. Transaction commits
10. `transaction_logs` entry updated to "committed"

---

### Task Assignment (with pessimistic locking)

```php
// TaskController.php

public function addAssignee(Request $request, int $task): RedirectResponse
{
    $task = Task::withoutGlobalScopes()->findOrFail($task);
    Gate::authorize('assign', $task);
    
    $validated = $request->validate([
        'user_id' => ['required', 'integer', 'exists:users,id'],
    ]);
    
    $user = User::findOrFail($validated['user_id']);
    
    // Use locked transaction to prevent race conditions
    return $this->withLockedTransaction(
        operationName: 'Add Task Assignee',
        entity: $task,
        callback: function ($lockedTask) use ($validated, $user, $request) {
            // This check happens AFTER acquiring the lock
            // So even if two requests check simultaneously, one will wait
            if ($lockedTask->assignedUsers()->where('user_id', $validated['user_id'])->exists()) {
                return back()->withErrors(['user_id' => 'User is already assigned.']);
            }
            
            $lockedTask->assignedUsers()->attach($validated['user_id'], [
                'assigned_by' => $request->user()->id,
                'assigned_date' => now(),
            ]);
            
            $lockedTask->logTaskActivity('assignee_added', [...]);
            
            return back()->with('success', "Assigned successfully.");
        },
        operationType: TransactionLog::TYPE_ASSIGN
    );
}
```

**Why pessimistic locking here?**

Without locking, this race condition could happen:
1. User A checks: "Is John assigned?" → No
2. User B checks: "Is John assigned?" → No
3. User A assigns John ✓
4. User B assigns John → DUPLICATE! (blocked by unique constraint, but error is confusing)

With locking:
1. User A acquires lock, checks, assigns, releases lock
2. User B waits... then checks and sees John is already assigned

---

## How These Features Protect the System

### 1. Race Conditions Prevention

**Scenario:** Two managers try to assign the same person at the exact same moment.

**Protection:** Pessimistic locking ensures one request waits for the other:
```
Request 1: LOCK task → check → assign → UNLOCK
Request 2: WAIT... → LOCK → check (already assigned!) → return error
```

### 2. Lost Update Prevention

**Scenario:** User A and B both open the same task. A changes priority. B changes status. B saves last, overwriting A's priority change.

**Protection:** Optimistic locking detects the conflict:
```
A loads task (version=5)
B loads task (version=5)
A saves priority → version=6
B tries to save → "Expected 5, got 6" → B must refresh first
```

### 3. Partial Commit Prevention

**Scenario:** A comment is created, but the activity log fails. Now we have an untracked comment.

**Protection:** Transactions ensure all-or-nothing:
```php
DB::transaction(function () {
    $comment = $task->comments()->create([...]); // ✓
    $task->logTaskActivity('comment_added', [...]); // ✗ fails
}); // Both rolled back - no orphan comment
```

### 4. Invalid State Prevention

**Scenario:** Someone uses a database admin tool to mark a task as "Done" without it going through review.

**Protection:** Database triggers block invalid transitions:
```sql
-- Trigger blocks this:
UPDATE tasks SET status_id = 4 WHERE id = 1; -- 4 = Done
-- Error: "Invalid status transition: To Do -> Done"
```

### 5. Audit Trail for Investigations

**Scenario:** A task's priority changed mysteriously. Who did it?

**Solution:** Query transaction logs:
```sql
SELECT actor.first_name, old_values, new_values, started_at
FROM transaction_logs
JOIN users actor ON transaction_logs.actor_id = actor.id
WHERE entity_type = 'App\\Models\\Task'
  AND entity_id = 123
  AND operation_name LIKE '%Priority%';
```

---

## Maintenance & Extension Guide

### When to Add Transactions

**DO wrap in transactions:**
- Any operation that modifies multiple tables
- Operations that must not partially complete
- Critical business operations
- Anything with an audit log entry

**DON'T need transactions:**
- Simple single-table reads
- Index page queries
- Single-field updates with no side effects

### When to Add Optimistic Locking

**DO add version columns to:**
- Any model frequently edited by multiple users
- Models where "last write wins" would cause problems
- Core business entities (Tasks, Projects, etc.)

**DON'T need version columns:**
- Lookup/enum tables (statuses, roles)
- Audit/log tables (write-only)
- Pivot tables without extra data

### When to Use Pessimistic (Row) Locking

**DO use row locks for:**
- High-frequency conflict operations (assignments, inventory)
- Operations where checking + modifying must be atomic
- Short-duration operations (< 2 seconds)

**DON'T use row locks:**
- Long-running operations (user will time out)
- Read-heavy operations
- Low-conflict updates (use optimistic locking instead)

### Adding a New Trigger

1. Create a migration:
```php
DB::unprepared("
    CREATE TRIGGER trg_your_trigger_name
    BEFORE UPDATE ON your_table
    FOR EACH ROW
    BEGIN
        -- Your logic
    END
");
```

2. Add to `down()` method:
```php
DB::unprepared('DROP TRIGGER IF EXISTS trg_your_trigger_name');
```

3. Test thoroughly in development first!

### Adding a New Stored Procedure

1. Create in migration file
2. Add to `StoredProcedureService.php`:
```php
public function yourProcedure(int $param1): array
{
    $results = DB::select('
        CALL sp_your_procedure(?, @status, @message);
        SELECT @status AS status, @message AS message;
    ', [$param1]);
    
    // Parse results...
}
```

---

## Troubleshooting

### Error: "Invalid status transition"

**Cause:** Database trigger rejected the status change.

**Solution:** Check the allowed transitions in the trigger definition. The task must follow the workflow:
```
To Do → In Progress → In Review → Done
```

**Workaround (admin only):** If you absolutely must bypass (e.g., data migration):
```sql
SET @DISABLE_TRIGGERS = TRUE;
UPDATE tasks SET status_id = ... WHERE id = ...;
SET @DISABLE_TRIGGERS = FALSE;
```

### Error: "Record was modified by another user"

**Cause:** Optimistic locking conflict.

**Solution for users:** Refresh the page and try again.

**Solution for developers:** Ensure frontend sends the `version` field:
```javascript
// Load
const task = await fetchTask(id);

// Save (include version)
await saveTask({ ...changes, version: task.version });
```

### Error: "Lock wait timeout exceeded"

**Cause:** Another transaction held a lock too long.

**Solutions:**
1. Check for long-running transactions
2. Increase timeout (not recommended for production)
3. Break operation into smaller transactions

**Monitoring:**
```sql
SHOW ENGINE INNODB STATUS; -- Look for "LATEST DETECTED DEADLOCK"
```

### Error: "Cannot create task in a deleted project"

**Cause:** Trigger preventing orphan tasks.

**Solution:** The project was soft-deleted. Either:
1. Restore the project first
2. Create the task in a different project

---

## Performance Considerations

### Index Usage

All locking operations use indexed columns:
- `tasks.id, tasks.version` — composite index for version checks
- `task_activity_log.task_id, created_at` — timeline queries
- `transaction_logs.entity_type, entity_id` — entity lookups

### Transaction Duration

**Rule:** Keep transactions as short as possible.

- ✓ Good: 50-500ms
- ⚠️ Warning: 1-5 seconds
- ✗ Bad: > 5 seconds (risk of lock timeouts)

### Transaction Log Retention

The `transaction_logs` table will grow. Set up archival:

```php
// In scheduled command
TransactionLog::where('started_at', '<', now()->subMonths(3))
    ->where('status', 'committed')
    ->delete();
```

### Trigger Performance

Triggers add ~1-5ms per operation. This is acceptable for:
- User-initiated actions (form submissions)
- API calls

But consider disabling for:
- Bulk imports (temporarily disable triggers)
- Data migrations

---

## Summary

This system provides enterprise-grade reliability:

| Feature | Protects Against | Applied To |
|---------|------------------|------------|
| Optimistic Locking | Lost updates | Tasks, Projects |
| Pessimistic Locking | Race conditions | Assignment operations |
| Transactions | Partial commits | All multi-step operations |
| Triggers | Invalid states | Status transitions, orphan prevention |
| Stored Procedures | Complex operation failures | Bulk updates, ownership transfers |
| Transaction Logs | Untracked changes | All critical operations |

The system is designed to be **maintainable** and **extendable**. Follow the patterns in this document when adding new features or modifying existing ones.

---

*Questions? Contact the senior engineering team or refer to the inline code documentation.*
