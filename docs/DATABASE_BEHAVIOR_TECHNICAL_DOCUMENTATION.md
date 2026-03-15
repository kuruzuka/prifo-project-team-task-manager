# Database Behavior & Concurrency Mechanisms — Technical Documentation

> **Generated:** March 12, 2026  
> **Database Engine:** MySQL 8.x  
> **Laravel Version:** 12.53.0  
> **PHP Version:** 8.4.1

This document provides exhaustive technical documentation of all database behavior and concurrency mechanisms implemented in this application. Each feature includes simple explanations, affected models, implementation locations, execution flows, code references, and database interactions.

---

## Table of Contents

1. [Activity Logging System](#1-activity-logging-system)
2. [Optimistic Locking](#2-optimistic-locking)
3. [Soft Delete & Hard Delete Prevention](#3-soft-delete--hard-delete-prevention)
4. [Database Triggers](#4-database-triggers)
5. [Stored Procedures](#5-stored-procedures)
6. [Transaction Management](#6-transaction-management)
7. [Pessimistic Locking](#7-pessimistic-locking)
8. [Concurrency Control Trait](#8-concurrency-control-trait)

---

## 1. Activity Logging System

### 1.1. What It Does (Explain Like I'm 5)

Imagine you have a notebook where you write down everything that happens to your toys. "Mom moved the teddy bear to the shelf at 3pm." Activity logging is like that notebook — it records every important change in the system so administrators can see what happened, who did it, and when it occurred.

The system has **three types of activity logs**:

| Log Type | Purpose | Table |
|----------|---------|-------|
| **Generic Activity Logs** | Polymorphic logs for any model | `activity_logs` |
| **Task Activity Logs** | Task-specific events (status, priority, progress) | `task_activity_log` |
| **Transaction Logs** | Transaction-level audit trail with before/after values | `transaction_logs` |

---

### 1.2. Which Models Use This Feature

#### Models Using `HasActivityLogs` Trait:

| Model | File Path | Line Number |
|-------|-----------|-------------|
| **Project** | [app/Models/Project.php](../app/Models/Project.php#L20) | Line 20 |
| **Task** | [app/Models/Task.php](../app/Models/Task.php#L18) | Line 18 |

```php
// app/Models/Project.php (Line 20)
use HasActivityLogs;

// app/Models/Task.php (Line 18)
use HasActivityLogs;
```

#### Models with Custom Activity Logging:

| Model | File Path | Method |
|-------|-----------|--------|
| **Task** | [app/Models/Task.php](../app/Models/Task.php#L97-L104) | `logTaskActivity()` |

---

### 1.3. Where It's Used in User Actions

Activity logging is triggered automatically when users perform actions that modify data. Here's a complete list of user actions that create activity logs:

#### TaskController Actions

| User Action | Method | Activity Type | What's Logged |
|-------------|--------|---------------|---------------|
| Create a task | `store()` | `task_created` | Task title, project ID |
| Edit task details | `update()` | `task_updated` | Changed fields with old/new values |
| Change task status | `updateStatus()` | `status_changed` | Old status → New status |
| Change task priority | `updatePriority()` | `priority_changed` | Old priority → New priority |
| Update progress | `updateProgress()` | `progress_updated` | Old progress % → New progress % |
| Assign user to task | `addAssignee()` | `assignee_added` | User ID, user name |
| Unassign user from task | `removeAssignee()` | `assignee_removed` | User ID, user name |

#### CommentController Actions

| User Action | Method | Activity Type | What's Logged |
|-------------|--------|---------------|---------------|
| Add comment to task | `store()` | `comment_added` | Comment ID, preview (first 100 chars) |
| Edit a comment | `update()` | `comment_edited` | Comment ID, old/new preview |

#### ProjectController Actions

| User Action | Method | Activity Type | What's Logged |
|-------------|--------|---------------|---------------|
| Edit project details | `update()` | `project_updated` | Changed fields with old/new values |
| Update project teams | `updateTeams()` | `teams_updated` | Teams added/removed, old/new team lists |

---

### 1.4. Where the Feature Is Implemented

#### HasActivityLogs Trait

**File:** [app/Concerns/HasActivityLogs.php](../app/Concerns/HasActivityLogs.php)

```php
<?php

namespace App\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivityLogs
{
    /**
     * Get all activity logs for this model
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }

    /**
     * Log an activity for this model
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function logActivity(string $activityType, ?array $metadata = null, ?int $actorId = null): ActivityLog
    {
        return $this->activityLogs()->create([
            'activity_type' => $activityType,
            'metadata' => $metadata,
            'actor_id' => $actorId ?? auth()->id(),
        ]);
    }
}
```

**What the trait does:**
- Provides a `activityLogs()` polymorphic relationship to retrieve all logs for a model
- Provides a `logActivity()` method to create new activity log entries
- Automatically captures the current authenticated user as the actor

---

### 1.5. Execution Flow

```
User updates a task's status
         ↓
TaskController::updateStatus()
[app/Http/Controllers/TaskController.php:662]
         ↓
withVersionCheck() wrapper executes
         ↓
$task->update(['status_id' => $newStatusId])
         ↓
$task->logTaskActivity('status_changed', [...])
[app/Models/Task.php:97-104]
         ↓
TaskActivityLog::create() inserts record
         ↓
Row inserted into `task_activity_log` table
```

---

### 1.6. Code References

#### Step 1: Controller calls update with logging

**File:** [app/Http/Controllers/TaskController.php](../app/Http/Controllers/TaskController.php#L672-L688)  
**Lines:** 672-688

```php
return $this->withVersionCheck(
    entity: $task,
    expectedVersion: $validated['version'] ?? null,
    callback: function () use ($task, $validated) {
        $oldStatus = $task->status?->name ?? 'None';
        $task->update(['status_id' => $validated['status_id']]);
        $task->load('status:id,name');
        $newStatus = $task->status->name;

        $task->logTaskActivity('status_changed', [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return back()->with('success', "Status changed from '{$oldStatus}' to '{$newStatus}'.");
    },
    operationName: 'Update Task Status'
);
```

#### Step 2: Task model logs the activity

**File:** [app/Models/Task.php](../app/Models/Task.php#L97-L104)  
**Lines:** 97-104

```php
public function logTaskActivity(string $activityType, ?array $metadata = null, ?int $actorId = null): TaskActivityLog
{
    return $this->taskActivityLogs()->create([
        'activity_type' => $activityType,
        'metadata' => $metadata,
        'actor_id' => $actorId ?? auth()->id(),
    ]);
}
```

---

### 1.7. Database Interaction

#### activity_logs table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint unsigned | Primary key |
| `loggable_type` | varchar(255) | Model class name (e.g., `App\Models\Project`) |
| `loggable_id` | bigint unsigned | ID of the logged model |
| `activity_type` | varchar(255) | Type of activity (e.g., `status_changed`) |
| `metadata` | json | Additional data about the activity |
| `actor_id` | bigint unsigned | User who performed the action |
| `created_at` | timestamp | When the activity occurred |
| `updated_at` | timestamp | Last update time |

**Insert example:**
```sql
INSERT INTO activity_logs 
(loggable_type, loggable_id, activity_type, metadata, actor_id, created_at, updated_at)
VALUES 
('App\\Models\\Project', 5, 'ownership_transferred', '{"old_manager_id": 3}', 1, NOW(), NOW());
```

#### task_activity_log table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint unsigned | Primary key |
| `task_id` | bigint unsigned | Task this log belongs to |
| `activity_type` | varchar(255) | Type (e.g., `status_changed`, `priority_changed`) |
| `metadata` | json | Activity details |
| `actor_id` | bigint unsigned | User who performed the action |
| `created_at` | timestamp | When the activity occurred |
| `updated_at` | timestamp | Last update time |

**Insert example:**
```sql
INSERT INTO task_activity_log 
(task_id, activity_type, metadata, actor_id, created_at, updated_at)
VALUES 
(42, 'status_changed', '{"old_status": "To Do", "new_status": "In Progress"}', 7, NOW(), NOW());
```

**Common activity types:**
- `task_created` — Task was created
- `status_changed` — Status was updated
- `priority_changed` — Priority was updated
- `progress_updated` — Progress percentage changed
- `assignee_added` — User was assigned
- `assignee_removed` — User was unassigned

---

## 2. Optimistic Locking

### 2.1. What It Does (Explain Like I'm 5)

Think of two people editing the same Google Doc, but **without** seeing each other's changes in real-time. Optimistic locking is like putting a "version number" sticker on the document. When you try to save, the system checks: "Is the sticker the same as when you started?" If someone else changed it, your sticker is old, and the system says: "Sorry, someone else already changed this. Please refresh and try again."

---

### 2.2. Which Models Use This Feature

| Model | File Path | Line Number | Version Column |
|-------|-----------|-------------|----------------|
| **Project** | [app/Models/Project.php](../app/Models/Project.php#L21) | Line 21 | `version` |
| **Task** | [app/Models/Task.php](../app/Models/Task.php#L19) | Line 19 | `version` |

```php
// app/Models/Project.php (Lines 20-21)
use HasActivityLogs;
use HasOptimisticLocking;

// app/Models/Task.php (Lines 18-19)
use HasActivityLogs;
use HasOptimisticLocking;
```

**Why these models?**
- Tasks and Projects have high-frequency updates from multiple users
- Comments are immutable after creation (only author can edit)
- Lookup tables (statuses, roles) are rarely updated

---

### 2.3. Where It's Used in User Actions

Optimistic locking protects updates from concurrent modification conflicts. The frontend sends a `version` field with each request, and the backend verifies it hasn't changed.

#### TaskController Actions Using Version Check

| User Action | Method | Location | What Happens on Conflict |
|-------------|--------|----------|-------------------------|
| Edit task details | `update()` | [TaskController.php#L598](../app/Http/Controllers/TaskController.php#L598) | Returns "This record was modified by another user" |
| Change status | `updateStatus()` | [TaskController.php#L672](../app/Http/Controllers/TaskController.php#L672) | Returns validation error, user must refresh |
| Change priority | `updatePriority()` | [TaskController.php#L707](../app/Http/Controllers/TaskController.php#L707) | Returns validation error, user must refresh |
| Update progress | `updateProgress()` | [TaskController.php#L740](../app/Http/Controllers/TaskController.php#L740) | Returns validation error, user must refresh |

#### How the Frontend Participates

The Vue/Inertia frontend must include the `version` field when submitting forms:

```typescript
// Example: Updating task status
router.patch(route('tasks.updateStatus', task.id), {
    status_id: newStatusId,
    version: task.version,  // ← Current version from page props
});
```

> **Note:** Project updates (`ProjectController::update()`) do not currently use optimistic locking — they could be enhanced to do so if concurrent editing becomes an issue.

---

### 2.4. Where the Feature Is Implemented

**File:** [app/Concerns/HasOptimisticLocking.php](../app/Concerns/HasOptimisticLocking.php)

The trait provides:

| Method | Purpose |
|--------|---------|
| `getVersionColumn()` | Returns the version column name (default: `version`) |
| `initializeHasOptimisticLocking()` | Sets default version=1 for new models |
| `saveWithVersionCheck($expectedVersion)` | Saves only if version matches |
| `updateWithVersionCheck($attributes, $expectedVersion)` | Fill + save with check |
| `freshVersion()` | Gets current version from database |
| `isStale()` | Checks if model differs from database |
| `scopeWhereVersion($query, $version)` | Query scope for version matching |

---

### 2.5. Execution Flow

```
Frontend sends update request with version
         ↓
Controller receives request
[app/Http/Controllers/TaskController.php:662]
         ↓
withVersionCheck() called
[app/Concerns/UsesConcurrencyControl.php:98-130]
         ↓
Check: Current DB version == Expected version?
         │
         ├── YES → Execute update callback
         │           ↓
         │         Task saved
         │           ↓
         │         DB trigger increments version
         │         [trg_tasks_increment_version]
         │           ↓
         │         Return success
         │
         └── NO → Throw StaleModelException
                   ↓
                 Return validation error to frontend
                 "This record was modified by another user"
```

---

### 2.6. Code References

#### Step 1: Controller uses withVersionCheck

**File:** [app/Http/Controllers/TaskController.php](../app/Http/Controllers/TaskController.php#L672-L688)  
**Lines:** 672-688

```php
return $this->withVersionCheck(
    entity: $task,
    expectedVersion: $validated['version'] ?? null,
    callback: function () use ($task, $validated) {
        $oldStatus = $task->status?->name ?? 'None';
        $task->update(['status_id' => $validated['status_id']]);
        // ...
    },
    operationName: 'Update Task Status'
);
```

#### Step 2: Concurrency control trait checks version

**File:** [app/Concerns/UsesConcurrencyControl.php](../app/Concerns/UsesConcurrencyControl.php#L98-L130)  
**Lines:** 98-130

```php
protected function withVersionCheck(
    Model $entity,
    ?int $expectedVersion,
    Closure $callback,
    string $operationName = 'Update Record'
): mixed {
    // If version is provided and entity supports optimistic locking
    if ($expectedVersion !== null && method_exists($entity, 'isStale')) {
        $currentVersion = $entity->freshVersion();

        if ($currentVersion !== $expectedVersion) {
            throw new StaleModelException(
                $entity,
                "This record was modified by another user. Please refresh and try again."
            );
        }
    }

    try {
        return $this->withTransaction($operationName, $entity, $callback);
    } catch (QueryException $e) {
        // Handle database trigger errors (SQLSTATE 45000)
        // ...
    }
}
```

#### Step 3: HasOptimisticLocking trait methods

**File:** [app/Concerns/HasOptimisticLocking.php](../app/Concerns/HasOptimisticLocking.php#L59-L95)  
**Lines:** 59-95

```php
public function saveWithVersionCheck(?int $expectedVersion = null, array $options = []): bool
{
    // For new models, just save normally
    if (!$this->exists) {
        return $this->save($options);
    }

    $versionColumn = static::getVersionColumn();

    // If no expected version provided, use the model's current version
    if ($expectedVersion === null) {
        $expectedVersion = $this->getOriginal($versionColumn);
    }

    // Check current database version
    $currentVersion = static::query()
        ->where($this->getKeyName(), $this->getKey())
        ->value($versionColumn);

    if ($currentVersion === null) {
        throw new StaleModelException(
            $this,
            'Record no longer exists (may have been deleted)'
        );
    }

    if ($currentVersion != $expectedVersion) {
        throw new StaleModelException(
            $this,
            "Record was modified by another user. Expected version {$expectedVersion}, found {$currentVersion}."
        );
    }

    // Version column will be auto-incremented by database trigger
    return $this->save($options);
}
```

---

### 2.7. Database Interaction

#### Version column migration

**File:** [database/migrations/2026_03_12_000001_add_version_columns_for_optimistic_locking.php](../database/migrations/2026_03_12_000001_add_version_columns_for_optimistic_locking.php)

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->unsignedBigInteger('version')->default(1)->after('created_by');
});

Schema::table('projects', function (Blueprint $table) {
    $table->unsignedBigInteger('version')->default(1)->after('end_date');
});
```

#### How version increments (via triggers)

The version column is **NOT** incremented by Laravel. It's incremented by **database triggers** to ensure atomicity:

```sql
-- Defined in: database/migrations/2026_03_12_000003_create_database_triggers.php

CREATE TRIGGER trg_tasks_increment_version
BEFORE UPDATE ON tasks
FOR EACH ROW
BEGIN
    SET NEW.version = OLD.version + 1;
END;

CREATE TRIGGER trg_projects_increment_version
BEFORE UPDATE ON projects
FOR EACH ROW
BEGIN
    SET NEW.version = OLD.version + 1;
END;
```

---

### 2.8. Concurrency Scenario

```
TIME    USER A                          USER B
────────────────────────────────────────────────────────────
T1      Load task (version=5)           
T2                                      Load task (version=5)
T3                                      Save changes
T4                                      ✓ Success! (version→6)
T5      Save changes
T6      ✗ REJECTED!
        "Expected version 5, found 6"
```

---

### 2.9. Exception Handling

**File:** [app/Exceptions/StaleModelException.php](../app/Exceptions/StaleModelException.php)

```php
class StaleModelException extends Exception
{
    protected Model $model;

    public function __construct(Model $model, string $message = 'Record was modified by another user.')
    {
        $this->model = $model;
        parent::__construct($message);
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    public function getModelId(): mixed
    {
        return $this->model->getKey();
    }
}
```

---

## 3. Soft Delete & Hard Delete Prevention

### 3.1. What It Does (Explain Like I'm 5)

Imagine a toy box with a "trash" section. When you want to throw away a toy, instead of actually throwing it in the garbage, you put it in the trash section of the toy box. You can still find it and take it back if you change your mind. But there's also a strict rule: nobody is allowed to permanently throw toys in the garbage — they can only go to the trash section.

**Soft Delete** = Moving to trash section (reversible)  
**Hard Delete** = Actually throwing away (irreversible) — BLOCKED!

---

### 3.2. Which Models Use This Feature

#### Models Using `SoftDeletes` + `PreventsHardDeletes`:

| Model | File Path | SoftDeletes Line | PreventsHardDeletes Line |
|-------|-----------|------------------|--------------------------|
| **Project** | [app/Models/Project.php](../app/Models/Project.php) | Line 19 | Line 22 |
| **Task** | [app/Models/Task.php](../app/Models/Task.php) | Line 21 | Line 21 |

```php
// app/Models/Project.php (Lines 19, 22)
use SoftDeletes;
use PreventsHardDeletes;

// app/Models/Task.php (Lines 20-21)
use SoftDeletes;
use PreventsHardDeletes;
```

---

### 3.3. Where It's Used in User Actions

Soft delete is triggered when users delete tasks or projects. Hard delete is **always blocked** by the `PreventsHardDeletes` trait.

#### User Actions That Trigger Soft Delete

| User Action | Controller Method | What Happens |
|-------------|-------------------|-------------|
| Delete a task | `TaskController::destroy()` | Sets `deleted_at` timestamp, task hidden from normal queries |
| Delete a project | `ProjectController::destroy()` | Sets `deleted_at` timestamp (blocked if project has active tasks by trigger) |
| Delete user account | `ProfileController::destroy()` | User soft-deleted, can be restored by admin |

#### Hard Delete Prevention

When any code attempts `$task->forceDelete()` or `$project->forceDelete()`:

1. The `PreventsHardDeletes` trait intercepts the operation
2. A **security warning** is logged with model details and user ID
3. The operation is **cancelled** — no data is deleted
4. This ensures **complete audit trail preservation**

```php
// This will be BLOCKED:
$task->forceDelete();  // ✗ Logged as security warning, no deletion

// This works (soft delete):
$task->delete();       // ✓ Sets deleted_at, preserves data
```

#### Querying Soft-Deleted Records

```php
// Normal query (excludes deleted)
Task::all();

// Include deleted
Task::withTrashed()->get();

// Only deleted
Task::onlyTrashed()->get();

// Restore a soft-deleted record
$task->restore();
```

---

### 3.4. Where the Feature Is Implemented

**File:** [app/Concerns/PreventsHardDeletes.php](../app/Concerns/PreventsHardDeletes.php)

```php
<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait to prevent hard deletes on models.
 *
 * This trait blocks all forceDelete operations to ensure audit integrity.
 * Models using this trait can only be soft-deleted.
 *
 * Security: Ensures complete audit trail preservation.
 */
trait PreventsHardDeletes
{
    /**
     * Boot the trait.
     */
    public static function bootPreventsHardDeletes(): void
    {
        static::forceDeleting(function (Model $model) {
            // Log the attempt for security monitoring
            logger()->warning('Hard delete attempt blocked', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'user_id' => auth()->id(),
            ]);

            return false; // Prevent the delete
        });
    }
}
```

**How it works:**
1. The trait hooks into Eloquent's `forceDeleting` event
2. When `$model->forceDelete()` is called, the callback intercepts it
3. It logs a security warning with model details
4. Returns `false` to cancel the operation

---

### 3.5. Execution Flow

```
Developer calls $task->forceDelete()
         ↓
Eloquent fires 'forceDeleting' event
         ↓
PreventsHardDeletes::bootPreventsHardDeletes() intercepts
         ↓
logger()->warning() records the attempt
         ↓
return false — operation cancelled
         ↓
Task remains in database (no deletion occurs)
```

---

### 3.6. Code References

#### Soft Delete Usage in Controller

**File:** [app/Http/Controllers/TaskController.php](../app/Http/Controllers/TaskController.php)

```php
public function destroy(Request $request, int $task): RedirectResponse
{
    $task = Task::withoutGlobalScopes()->findOrFail($task);
    Gate::authorize('delete', $task);

    $taskName = $task->title;
    $projectId = $task->project_id;

    $task->delete(); // This is a SOFT delete because Task uses SoftDeletes

    return redirect($redirectUrl)
        ->with('success', "Task '{$taskName}' has been deleted.");
}
```

---

### 3.7. Database Interaction

When `SoftDeletes` is used, the table has a `deleted_at` column:

| Column | Type | Value When Active | Value When Deleted |
|--------|------|-------------------|-------------------|
| `deleted_at` | timestamp | `NULL` | Deletion timestamp |

**Soft delete query:**
```sql
UPDATE tasks SET deleted_at = NOW() WHERE id = 42;
```

**Laravel automatically excludes soft-deleted records:**
```sql
-- Task::all() generates:
SELECT * FROM tasks WHERE deleted_at IS NULL;

-- To include deleted:
SELECT * FROM tasks; -- Task::withTrashed()->get()
```

---

## 4. Database Triggers

### 4.1. What It Does (Explain Like I'm 5)

Think of database triggers like automatic rules that a robot follows. When something happens (like changing a task), the robot automatically does something else (like updating a number). The robot never forgets and can't be tricked — it always follows the rules, even if the person making changes doesn't know about the rules.

---

### 4.2. Triggers Implemented

**File:** [database/migrations/2026_03_12_000003_create_database_triggers.php](../database/migrations/2026_03_12_000003_create_database_triggers.php)

| Trigger Name | Fires On | Purpose |
|--------------|----------|---------|
| `trg_tasks_increment_version` | BEFORE UPDATE on `tasks` | Auto-increment version for optimistic locking |
| `trg_projects_increment_version` | BEFORE UPDATE on `projects` | Auto-increment version for optimistic locking |
| `trg_tasks_validate_status_transition` | BEFORE UPDATE on `tasks` | Prevent invalid status transitions |
| `trg_tasks_auto_complete_progress` | BEFORE UPDATE on `tasks` | Auto-set progress based on status |
| `trg_prevent_orphan_tasks` | BEFORE INSERT on `tasks` | Block tasks in soft-deleted projects |
| `trg_prevent_project_delete_with_tasks` | BEFORE UPDATE on `projects` | Block soft-delete if project has active tasks |

> **Note:** Triggers only work with MySQL/MariaDB. SQLite (used in tests) does not support triggers, so business rules are also enforced at the application level.

---

### 4.3. When Triggers Are Activated by User Actions

Database triggers fire **automatically** when specific database operations occur. Users don't call them directly — they activate as a side effect of user actions:

#### Version Increment Triggers

| User Action | Trigger Activated | Result |
|-------------|-------------------|--------|
| Edit task details | `trg_tasks_increment_version` | `version` column increments (e.g., 5 → 6) |
| Change task status | `trg_tasks_increment_version` | `version` column increments |
| Update task priority | `trg_tasks_increment_version` | `version` column increments |
| Update task progress | `trg_tasks_increment_version` | `version` column increments |
| Edit project details | `trg_projects_increment_version` | Project `version` increments |

#### Status Transition Validation

| User Action | Trigger | Result |
|-------------|---------|--------|
| Change status: To Do → In Progress | `trg_tasks_validate_status_transition` | ✓ **Allowed** |
| Change status: To Do → Done | `trg_tasks_validate_status_transition` | ✗ **BLOCKED** — Returns SQLSTATE 45000 error |
| Change status: In Progress → In Review | `trg_tasks_validate_status_transition` | ✓ **Allowed** |
| Change status: Done → To Do | `trg_tasks_validate_status_transition` | ✗ **BLOCKED** — Must go through In Review first |

#### Progress Auto-Completion

| User Action | Trigger | Result |
|-------------|---------|--------|
| Change status to Done | `trg_tasks_auto_complete_progress` | Progress automatically set to **100%** |
| Change status back to To Do | `trg_tasks_auto_complete_progress` | Progress automatically set to **0%** |

#### Data Integrity Protection

| User Action | Trigger | Result |
|-------------|---------|--------|
| Create task in deleted project | `trg_prevent_orphan_tasks` | ✗ **BLOCKED** — "Cannot create task in a deleted project" |
| Delete project with active tasks | `trg_prevent_project_delete_with_tasks` | ✗ **BLOCKED** — "Cannot delete project with active tasks" |

---

### 4.4. Trigger 1: Version Auto-Increment

**Purpose:** Enable optimistic locking without manual version management.

```sql
CREATE TRIGGER trg_tasks_increment_version
BEFORE UPDATE ON tasks
FOR EACH ROW
BEGIN
    SET NEW.version = OLD.version + 1;
END;
```

**Execution flow:**
```
UPDATE tasks SET title = 'New Title' WHERE id = 42
         ↓
Trigger fires BEFORE the update executes
         ↓
NEW.version = OLD.version + 1 (e.g., 5 → 6)
         ↓
Row is updated with new version value
```

---

### 4.5. Trigger 2: Status Transition Validation

**Purpose:** Enforce workflow rules — tasks must follow a proper lifecycle.

**Allowed transitions:**
```
To Do ──────→ In Progress
   ↑              │
   └──────────────┘ (can go back)
                  │
                  ↓
            In Review
              ↑   │
              │   ↓
              └── Done (can reopen for review)
```

**Blocked transitions:**
- To Do → Done (must go through workflow)
- To Do → In Review (must start work first)
- Done → To Do (must go through In Review → In Progress first)

```sql
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
                SET is_valid_transition = TRUE;
        END CASE;

        IF NOT is_valid_transition THEN
            SET error_msg = CONCAT('Invalid status transition: ', 
                                   COALESCE(old_status_name, 'NULL'), 
                                   ' -> ', 
                                   COALESCE(new_status_name, 'NULL'));
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
        END IF;
    END IF;
END;
```

**How SQLSTATE '45000' is handled:**

**File:** [app/Concerns/UsesConcurrencyControl.php](../app/Concerns/UsesConcurrencyControl.php#L118-L130)

```php
try {
    return $this->withTransaction($operationName, $entity, $callback);
} catch (QueryException $e) {
    // Handle database trigger errors (SQLSTATE 45000)
    $isTriggerError = $e->getCode() === '45000'
        || $e->getCode() === 45000
        || str_contains($e->getMessage(), 'SQLSTATE[45000]');

    if ($isTriggerError) {
        $message = $this->extractTriggerErrorMessage($e);
        throw ValidationException::withMessages([
            'database' => [$message],
        ]);
    }

    throw $e;
}
```

---

### 4.6. Trigger 3: Auto-Complete Progress

**Purpose:** Keep progress consistent with status.

```sql
CREATE TRIGGER trg_tasks_auto_complete_progress
BEFORE UPDATE ON tasks
FOR EACH ROW
BEGIN
    DECLARE new_status_name VARCHAR(50);

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
END;
```

---

### 4.7. Trigger 4: Prevent Orphan Tasks

**Purpose:** Block creating tasks in soft-deleted projects.

```sql
CREATE TRIGGER trg_prevent_orphan_tasks
BEFORE INSERT ON tasks
FOR EACH ROW
BEGIN
    DECLARE project_deleted_at TIMESTAMP;

    SELECT deleted_at INTO project_deleted_at
    FROM projects
    WHERE id = NEW.project_id;

    IF project_deleted_at IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot create task in a deleted project';
    END IF;
END;
```

---

### 4.8. Trigger 5: Prevent Project Delete with Tasks

**Purpose:** Block soft-deleting projects that have active tasks.

```sql
CREATE TRIGGER trg_prevent_project_delete_with_tasks
BEFORE UPDATE ON projects
FOR EACH ROW
BEGIN
    DECLARE active_task_count INT;

    -- Only check when soft-deleting
    IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
        SELECT COUNT(*) INTO active_task_count
        FROM tasks
        WHERE project_id = OLD.id AND deleted_at IS NULL;

        IF active_task_count > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Cannot delete project with active tasks. Delete or reassign tasks first.';
        END IF;
    END IF;
END;
```

---

## 5. Stored Procedures

### 5.1. What It Does (Explain Like I'm 5)

Think of stored procedures like a recipe card in a kitchen. Instead of telling the chef each step one by one ("get the flour, get the eggs, mix them..."), you just say "make pancakes" and the chef knows all the steps. It's faster, and the chef won't forget any steps.

Stored procedures are pre-written programs that live inside the database. When Laravel needs to do something complex, it just calls the procedure by name instead of sending many separate commands.

---

### 5.2. Procedures Implemented

**File:** [database/migrations/2026_03_12_000004_create_stored_procedures.php](../database/migrations/2026_03_12_000004_create_stored_procedures.php)

| Procedure Name | Purpose |
|----------------|---------|
| `sp_assign_task_with_audit` | Atomically assign user to task + create audit log |
| `sp_bulk_update_task_status` | Update multiple tasks' status in one transaction |
| `sp_transfer_project_ownership` | Transfer project manager with full audit trail |
| `sp_archive_completed_tasks` | Batch soft-delete old completed tasks |

---

### 5.3. Where Stored Procedures Are Called

Stored procedures are called from Laravel via the `StoredProcedureService`. Here's where each procedure is used:

#### sp_assign_task_with_audit

| Where Called | When | Purpose |
|--------------|------|--------|
| `StoredProcedureService::assignTaskWithAudit()` | Alternative to Laravel-based assignment | Atomic assignment at database level with row lock |

> **Note:** Currently, task assignment primarily uses `TaskController::addAssignee()` with `withLockedTransaction()`. The stored procedure is available for batch operations or direct database access.

#### sp_bulk_update_task_status

| Where Called | When | Purpose |
|--------------|------|--------|
| `StoredProcedureService::bulkUpdateTaskStatus()` | Bulk status changes | Update multiple tasks atomically with validation per task |

#### sp_transfer_project_ownership

| Where Called | When | Purpose |
|--------------|------|--------|
| `StoredProcedureService::transferProjectOwnership()` | Manager reassignment | Transfer project manager with full audit trail |

#### sp_archive_completed_tasks

| Where Called | When | Purpose |
|--------------|------|--------|
| `ArchiveCompletedTasks` command | `php artisan tasks:archive` | Batch soft-delete old completed tasks |
| Scheduled task | Daily (if configured) | Automatic cleanup of tasks completed > 30 days ago |

**Example: Running the archive command:**
```bash
# Archive tasks completed more than 30 days ago
php artisan tasks:archive

# Archive tasks completed more than 60 days ago
php artisan tasks:archive --days=60

# Preview without archiving
php artisan tasks:archive --dry-run
```

---

### 5.4. Procedure 1: Assign Task with Audit

**Purpose:** Assign a user to a task atomically, preventing race conditions.

```sql
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
END;
```

---

### 5.5. Calling Stored Procedures from Laravel

**File:** [app/Services/StoredProcedureService.php](../app/Services/StoredProcedureService.php)

```php
/**
 * Assign a user to a task using stored procedure.
 */
public function assignTaskWithAudit(int $taskId, int $userId, int $assignedBy): array
{
    $results = DB::select('
        CALL sp_assign_task_with_audit(?, ?, ?, @status, @message);
        SELECT @status AS status, @message AS message;
    ', [$taskId, $userId, $assignedBy]);

    $output = collect($results)->last();

    return [
        'status' => $output->status ?? self::STATUS_ERROR,
        'message' => $output->message ?? 'Unknown error',
    ];
}

/**
 * Check if a stored procedure call was successful.
 */
public function isSuccess(array $result): bool
{
    return ($result['status'] ?? '') === self::STATUS_SUCCESS;
}
```

**Usage in controller:**
```php
$procedureService = app(StoredProcedureService::class);
$result = $procedureService->assignTaskWithAudit($taskId, $userId, auth()->id());

if ($procedureService->isSuccess($result)) {
    return back()->with('success', $result['message']);
} else {
    return back()->with('error', $result['message']);
}
```

---

### 5.6. Procedure 2: Bulk Update Task Status

**Purpose:** Update multiple tasks atomically with validation.

```sql
CREATE PROCEDURE sp_bulk_update_task_status(
    IN p_task_ids JSON,
    IN p_new_status_id BIGINT,
    IN p_actor_id BIGINT,
    OUT p_success_count INT,
    OUT p_failed_count INT,
    OUT p_failed_tasks JSON
)
```

**Laravel interface:**
```php
public function bulkUpdateTaskStatus(array $taskIds, int $newStatusId, int $actorId): array
{
    $taskIdsJson = json_encode($taskIds);

    $results = DB::select('
        CALL sp_bulk_update_task_status(?, ?, ?, @success, @failed, @failed_tasks);
        SELECT @success AS success_count, @failed AS failed_count, @failed_tasks AS failed_tasks;
    ', [$taskIdsJson, $newStatusId, $actorId]);

    $output = collect($results)->last();

    return [
        'success_count' => (int) ($output->success_count ?? 0),
        'failed_count' => (int) ($output->failed_count ?? 0),
        'failed_tasks' => json_decode($output->failed_tasks ?? '[]', true),
    ];
}
```

---

## 6. Transaction Management

### 6.1. What It Does (Explain Like I'm 5)

Imagine you're transferring water from one bucket to another. A transaction is like saying: "Either ALL the water gets transferred, or NONE of it does." If something goes wrong in the middle (like the bucket tips over), all the water magically goes back to the first bucket. This way, you never end up with water spilled on the floor.

---

### 6.2. Where the Feature Is Implemented

**File:** [app/Services/TransactionManager.php](../app/Services/TransactionManager.php)

The `TransactionManager` provides:

| Method | Purpose |
|--------|---------|
| `execute()` | Run callback in a logged transaction |
| `executeSimple()` | Run callback in a simple transaction (no logging) |
| `executeWithLock()` | Run with pessimistic row lock |
| `executeBatch()` | Run multiple operations in one transaction |

---

### 6.3. Where Transactions Are Used in User Actions

Transactions are used throughout the application to ensure data integrity. Here's a complete map of user actions that use transactions:

#### TaskController — All Modifying Actions Use Transactions

| User Action | Transaction Type | Method Used | Logged |
|-------------|-----------------|-------------|--------|
| Create task | `DB::transaction()` | Direct | No |
| Edit task details | `withVersionCheck()` → `withTransaction()` | Trait | Yes |
| Change status | `withVersionCheck()` → `withTransaction()` | Trait | Yes |
| Change priority | `withVersionCheck()` → `withTransaction()` | Trait | Yes |
| Update progress | `withVersionCheck()` → `withTransaction()` | Trait | Yes |
| Add assignee | `withLockedTransaction()` | Trait | Yes |
| Remove assignee | `withLockedTransaction()` | Trait | Yes |

#### CommentController — Comment Operations

| User Action | Transaction Type | Method Used | Logged |
|-------------|-----------------|-------------|--------|
| Add comment | `withTransaction()` | Trait | Yes |
| Edit comment | `withTransaction()` | Trait | Yes |

#### Why Transactions Matter Here

1. **Atomicity**: If logging fails, the update is rolled back too
2. **Consistency**: Either all changes succeed or none do
3. **Audit Trail**: Every transaction creates a `TransactionLog` entry with:
   - `transaction_id` (UUID)
   - `old_values` / `new_values` (JSON snapshots)
   - `duration_ms` (performance tracking)
   - `status` (started, committed, failed, rolled_back)

#### Transaction Log Use Cases

| Scenario | What Gets Logged |
|----------|------------------|
| Successful task update | status=committed, duration_ms, new_values |
| Concurrent edit conflict | status=failed, error_message="Record was modified by another user" |
| Invalid status transition | status=failed, error_message from database trigger |
| Database connection error | status=rolled_back, error_message |

---

### 6.4. Transaction Log Table

**File:** [database/migrations/2026_03_12_000002_create_transaction_logs_table.php](../database/migrations/2026_03_12_000002_create_transaction_logs_table.php)

| Column | Type | Purpose |
|--------|------|---------|
| `transaction_id` | uuid | Groups related operations |
| `operation_type` | varchar(50) | create, update, delete, batch, etc. |
| `operation_name` | varchar(100) | Human-readable action name |
| `entity_type` | varchar(100) | Model class name |
| `entity_id` | bigint | Primary key of entity |
| `actor_id` | bigint | User who performed the action |
| `actor_ip` | varchar(45) | User's IP address |
| `actor_user_agent` | varchar(500) | User's browser info |
| `old_values` | json | State before change |
| `new_values` | json | State after change |
| `context` | json | Additional context (route, params) |
| `status` | enum | started, committed, rolled_back, failed |
| `error_message` | text | Error details if failed |
| `duration_ms` | int | How long the operation took |
| `started_at` | timestamp(6) | When operation started |
| `completed_at` | timestamp(6) | When operation finished |

---

### 6.5. Execution Flow

```
Controller calls withTransaction()
[app/Concerns/UsesConcurrencyControl.php:44]
         ↓
TransactionManager::execute() starts
         ↓
1. Generate UUID transaction_id
2. Record start time
3. Create TransactionLog entry (status='started')
         ↓
DB::transaction() wraps the callback
         ↓
   ┌─────────────────────┐
   │ Execute callback    │
   │ (create, update,    │
   │  delete operations) │
   └─────────────────────┘
         │
         ├── SUCCESS:
         │   ├── Calculate duration_ms
         │   ├── Capture new_values
         │   ├── Update log (status='committed')
         │   └── Return result
         │
         └── EXCEPTION:
             ├── Calculate duration_ms
             ├── Update log (status='failed' or 'rolled_back')
             ├── Record error_message
             └── Re-throw exception
```

---

### 6.6. Code References

#### TransactionManager execute method

**File:** [app/Services/TransactionManager.php](../app/Services/TransactionManager.php#L47-L97)  
**Lines:** 47-97

```php
public function execute(
    string $operationType,
    string $operationName,
    ?Model $entity,
    Closure $callback,
    ?array $oldValues = null,
    array $context = [],
    int $attempts = 1
): mixed {
    $transactionId = Str::uuid()->toString();
    $startTime = microtime(true);

    // Create the transaction log entry
    $log = $this->createLogEntry(
        $transactionId,
        $operationType,
        $operationName,
        $entity,
        $oldValues,
        $context
    );

    try {
        // Execute within database transaction
        $result = DB::transaction(function () use ($callback) {
            return $callback();
        }, $attempts);

        // Calculate duration
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        // Capture new values from result if it's a model
        $newValues = $this->captureNewValues($result, $entity);

        // Update log with success
        $log->update([
            'status' => TransactionLog::STATUS_COMMITTED,
            'new_values' => $newValues,
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);

        return $result;

    } catch (Throwable $e) {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $status = $this->isDeadlockError($e)
            ? TransactionLog::STATUS_ROLLED_BACK
            : TransactionLog::STATUS_FAILED;

        $log->update([
            'status' => $status,
            'error_message' => $e->getMessage(),
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);

        throw $e;
    }
}
```

---

### 6.7. Usage Example in Controller

**File:** [app/Http/Controllers/TaskController.php](../app/Http/Controllers/TaskController.php#L639-L660)

```php
public function store(TaskStoreRequest $request): RedirectResponse
{
    $validated = $request->validated();
    $user = $request->user();

    $task = DB::transaction(function () use ($validated, $defaultStatus, $user) {
        $task = Task::create([
            'project_id' => $validated['project_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'status_id' => $defaultStatus->id,
            'due_date' => $validated['due_date'] ?? null,
            'created_by' => $user->id,
        ]);

        // Log task creation activity
        $task->logTaskActivity('task_created', [
            'title' => $task->title,
            'project_id' => $task->project_id,
        ], $user->id);

        return $task;
    });

    // If anything fails, both task creation AND activity log are rolled back
}
```

---

## 7. Pessimistic Locking

### 7.1. What It Does (Explain Like I'm 5)

Imagine a bathroom with one toilet. When someone goes in, they lock the door. Anyone else who wants to use it has to wait outside. That's pessimistic locking — when you're working on something, you put a "lock" on it so nobody else can touch it until you're done.

---

### 7.2. Where the Feature Is Implemented

**File:** [app/Services/LockManager.php](../app/Services/LockManager.php)

The `LockManager` provides:

| Method | Lock Type | Purpose |
|--------|-----------|---------|
| `withRowLock()` | SELECT FOR UPDATE | Exclusive database row lock |
| `withSharedLock()` | LOCK IN SHARE MODE | Shared read lock |
| `withAdvisoryLock()` | Cache-based | Application-level named lock |
| `withModelLock()` | Cache-based | Convenience for model locking |

---

### 7.3. Where Locking Is Used in the Application

Pessimistic locking is used for operations that have **check-then-act** patterns where race conditions could cause data corruption or duplicate entries.

#### TaskController — Assignment Operations

| Action | Method | Location | Why Locking Is Needed |
|--------|--------|----------|----------------------|
| **Add Task Assignee** | `addAssignee()` | [TaskController.php#L774](../app/Http/Controllers/TaskController.php#L774) | Prevents two users from assigning the same person simultaneously |
| **Remove Task Assignee** | `removeAssignee()` | [TaskController.php#L811](../app/Http/Controllers/TaskController.php#L811) | Prevents race condition when multiple users unassign simultaneously |

**Example — Add Assignee with Lock:**

```php
return $this->withLockedTransaction(
    operationName: 'Add Task Assignee',
    entity: $task,
    callback: function ($lockedTask) use ($validated, $user, $request) {
        // Check if already assigned (after acquiring lock to prevent race)
        if ($lockedTask->assignedUsers()->where('user_id', $validated['user_id'])->exists()) {
            return back()->withErrors(['user_id' => 'User is already assigned to this task.']);
        }

        $lockedTask->assignedUsers()->attach($validated['user_id'], [
            'assigned_by' => $request->user()->id,
            'assigned_date' => now(),
        ]);

        $lockedTask->logTaskActivity('assignee_added', [
            'user_id' => $user->id,
            'user_name' => $user->first_name . ' ' . $user->last_name,
        ]);

        return back()->with('success', "{$user->first_name} {$user->last_name} has been assigned.");
    },
    operationType: TransactionLog::TYPE_ASSIGN
);
```

#### Stored Procedure — Database-Level Lock

The `sp_assign_task_with_audit` stored procedure also uses `SELECT FOR UPDATE` to prevent duplicate assignments at the database level:

```sql
-- Check if already assigned (using SELECT FOR UPDATE for concurrency)
ELSEIF EXISTS (
    SELECT 1 FROM task_assignments
    WHERE task_id = p_task_id AND user_id = p_user_id
    FOR UPDATE
) THEN
    SET p_status = 'ALREADY_ASSIGNED';
```

#### Why These Operations Need Locks (Not Optimistic Locking)

| Scenario | Problem Without Lock | Solution |
|----------|---------------------|----------|
| Two admins assign same user | Both check "is assigned?" → Both get FALSE → Both insert → **Duplicate entry** | Lock the task row first |
| Admin A assigns, Admin B unassigns simultaneously | Race condition on `task_assignments` pivot table | Lock ensures sequential execution |

> **Note:** Status/priority/progress updates use **optimistic locking** (version checks) instead, because conflicts there are recoverable — the user simply refreshes and retries. Assignment conflicts are not recoverable without data cleanup.

---

### 7.4. Database Row Lock

**File:** [app/Services/LockManager.php](../app/Services/LockManager.php#L43-L73)

```php
public function withRowLock(Model $model, Closure $callback, ?int $waitTimeout = null): mixed
{
    $waitTimeout = $waitTimeout ?? $this->defaultWaitTimeout;

    return DB::transaction(function () use ($model, $callback, $waitTimeout) {
        // Set lock wait timeout for this session
        DB::statement("SET innodb_lock_wait_timeout = {$waitTimeout}");

        try {
            // Acquire exclusive lock on the row
            $lockedModel = $model::query()
                ->where($model->getKeyName(), $model->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedModel) {
                throw new LockAcquisitionException(
                    "Cannot lock {$model->getTable()} #{$model->getKey()}: record not found"
                );
            }

            return $callback($lockedModel);

        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isLockTimeoutError($e)) {
                throw new LockAcquisitionException(
                    "Cannot lock {$model->getTable()} #{$model->getKey()}: lock timeout exceeded",
                    previous: $e
                );
            }
            throw $e;
        }
    });
}
```

---

### 7.5. Advisory Lock (Cache-based)

**Purpose:** For longer operations or cross-transaction coordination.

```php
public function withAdvisoryLock(
    string $key,
    Closure $callback,
    ?int $timeout = null,
    ?int $waitTimeout = null
): mixed {
    $timeout = $timeout ?? $this->defaultTimeout;
    $waitTimeout = $waitTimeout ?? $this->defaultWaitTimeout;

    $lock = Cache::lock("advisory_lock:{$key}", $timeout);

    $acquired = $lock->block($waitTimeout);

    if (!$acquired) {
        throw new LockAcquisitionException(
            "Cannot acquire advisory lock '{$key}': timeout after {$waitTimeout} seconds"
        );
    }

    try {
        return $callback();
    } finally {
        $lock->release();
    }
}
```

---

### 7.6. Usage via UsesConcurrencyControl

**File:** [app/Concerns/UsesConcurrencyControl.php](../app/Concerns/UsesConcurrencyControl.php#L72-L90)

```php
protected function withLockedTransaction(
    string $operationName,
    Model $entity,
    Closure $callback,
    string $operationType = TransactionLog::TYPE_UPDATE,
    array $context = []
): mixed {
    return $this->getTransactionManager()->executeWithLock(
        $operationType,
        $operationName,
        $entity,
        $callback,
        $context
    );
}
```

---

### 7.7. Exception Handling

**File:** [app/Exceptions/LockAcquisitionException.php](../app/Exceptions/LockAcquisitionException.php)

```php
class LockAcquisitionException extends Exception
{
    public function __construct(
        string $message = 'Could not acquire lock',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function report(): bool
    {
        logger()->warning('Lock acquisition failed', [
            'message' => $this->getMessage(),
            'user_id' => auth()->id(),
            'url' => request()->path(),
        ]);

        return false; // Allow default handling to continue
    }
}
```

---

## 8. Concurrency Control Trait

### 8.1. What It Does (Explain Like I'm 5)

Think of this trait like a helper that sits next to a controller. When the controller needs to do something important (like update a task), it asks the helper: "Please make sure this is done safely." The helper then:
1. Checks if anyone else changed the data
2. Wraps everything in a transaction
3. Records what happened
4. Handles any problems gracefully

---

### 8.2. Where the Feature Is Implemented

**File:** [app/Concerns/UsesConcurrencyControl.php](../app/Concerns/UsesConcurrencyControl.php)

---

### 8.3. Which Controllers Use This Trait

| Controller | File Path | Line Number |
|------------|-----------|-------------|
| **TaskController** | [app/Http/Controllers/TaskController.php](../app/Http/Controllers/TaskController.php#L24) | Line 24 |
| **CommentController** | [app/Http/Controllers/CommentController.php](../app/Http/Controllers/CommentController.php#L15) | Line 15 |

---

### 8.4. Where the Trait Methods Are Used

The `UsesConcurrencyControl` trait provides five methods. Here's exactly where each method is called:

#### `withVersionCheck()` — Optimistic Locking Operations

| Controller | Method | User Action |
|------------|--------|-------------|
| TaskController | `update()` | Edit task details (title, description, priority, due_date) |
| TaskController | `updateStatus()` | Change task status |
| TaskController | `updatePriority()` | Change task priority |
| TaskController | `updateProgress()` | Update progress percentage |

#### `withLockedTransaction()` — Pessimistic Locking Operations

| Controller | Method | User Action |
|------------|--------|-------------|
| TaskController | `addAssignee()` | Assign user to task |
| TaskController | `removeAssignee()` | Unassign user from task |

#### `withTransaction()` — Simple Logged Transactions

| Controller | Method | User Action |
|------------|--------|-------------|
| CommentController | `store()` | Add comment to task |
| CommentController | `update()` | Edit existing comment |

#### Why Different Methods for Different Actions

| Method | Best For | Example |
|--------|----------|--------|
| `withVersionCheck()` | High-frequency updates where conflicts are rare but possible | Status changes, progress updates |
| `withLockedTransaction()` | Check-then-act operations prone to race conditions | Assignment operations |
| `withTransaction()` | Operations needing atomicity but no concurrent edit concerns | Adding comments |
| `withSimpleTransaction()` | Quick operations without audit logging | (Not currently used) |
| `withBatchTransaction()` | Multiple operations that must all succeed | (Available for future bulk operations) |

---

### 8.5. Methods Provided (Summary)

| Method | Purpose | Uses |
|--------|---------|------|
| `withTransaction()` | Execute callback in a logged transaction | TransactionManager |
| `withLockedTransaction()` | Execute with pessimistic row lock | TransactionManager + LockManager |
| `withVersionCheck()` | Execute with optimistic locking validation | TransactionManager |
| `withBatchTransaction()` | Execute multiple operations atomically | TransactionManager |
| `withSimpleTransaction()` | Simple transaction without logging | DB::transaction |

---

### 8.6. Complete Execution Flow for Task Update

```
1. Frontend sends PUT /tasks/42/status
   with { status_id: 3, version: 5 }
         ↓
2. TaskController::updateStatus() receives request
   [app/Http/Controllers/TaskController.php:662]
         ↓
3. $this->withVersionCheck() called
   [app/Concerns/UsesConcurrencyControl.php:98]
         ↓
4. Check: entity->freshVersion() == expectedVersion?
         │
         ├─ NO → throw StaleModelException
         │       → Converted to ValidationException
         │       → Frontend shows "This record was modified..."
         │
         └─ YES → Continue
                 ↓
5. $this->withTransaction() called
   [app/Concerns/UsesConcurrencyControl.php:44]
         ↓
6. TransactionManager::execute() starts
   [app/Services/TransactionManager.php:47]
         ↓
7. Create TransactionLog entry (status='started')
         ↓
8. DB::transaction() wraps callback
         ↓
9. Execute callback:
   a. $task->update(['status_id' => 3])
      ↓
   b. TRIGGER trg_tasks_validate_status_transition fires
      ├─ Valid transition → proceeds
      └─ Invalid → SIGNAL SQLSTATE '45000'
         ↓
   c. TRIGGER trg_tasks_increment_version fires
      version: 5 → 6
         ↓
   d. TRIGGER trg_tasks_auto_complete_progress fires
      (if status = Done → progress = 100)
         ↓
   e. $task->logTaskActivity('status_changed', [...])
         ↓
   f. INSERT INTO task_activity_log
         ↓
10. Transaction commits successfully
         ↓
11. Update TransactionLog (status='committed', duration_ms=...)
         ↓
12. Return redirect with success message
```

---

### 8.7. Error Handling Flow

```
Exception thrown during callback
         ↓
   ┌─────────────────────────────────────────────────────────┐
   │ Is it QueryException with SQLSTATE[45000]?              │
   │ (Database trigger rejection)                            │
   └─────────────────────────────────────────────────────────┘
         │
         ├─ YES → extractTriggerErrorMessage()
         │        → throw ValidationException(['database' => $message])
         │        → Frontend displays trigger's error message
         │        → e.g., "Invalid status transition: To Do -> Done"
         │
         └─ NO → Re-throw original exception
                 → TransactionLog updated (status='failed')
                 → Laravel handles error (500, logging, etc.)
```

---

## Summary: How Everything Works Together

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                              APPLICATION LAYER                                  │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                │
│  Controller                                                                    │
│  (TaskController)                                                              │
│       │                                                                        │
│       │ uses                                                                   │
│       ▼                                                                        │
│  UsesConcurrencyControl ──────────────► TransactionManager                    │
│  (Trait)                                (Service)                              │
│       │                                     │                                  │
│       │ wraps operations                    │ creates                          │
│       ▼                                     ▼                                  │
│  withVersionCheck()                   TransactionLog                           │
│  withTransaction()                    (Audit records)                          │
│  withLockedTransaction()                                                       │
│                                                                                │
├────────────────────────────────────────────────────────────────────────────────┤
│                               MODEL LAYER                                       │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                │
│  Task / Project                                                                │
│       │                                                                        │
│       │ uses                                                                   │
│       ▼                                                                        │
│  ┌─────────────────────┐  ┌────────────────────┐  ┌─────────────────────────┐ │
│  │ HasActivityLogs     │  │ HasOptimisticLocking│  │ PreventsHardDeletes    │ │
│  │ (polymorphic logs)  │  │ (version checking)  │  │ (blocks forceDelete)   │ │
│  └─────────────────────┘  └────────────────────┘  └─────────────────────────┘ │
│       │                          │                                             │
│       │                          │                                             │
│       ▼                          ▼                                             │
│  activity_logs              Reads version from DB                              │
│  task_activity_log          (freshVersion())                                   │
│                                                                                │
├────────────────────────────────────────────────────────────────────────────────┤
│                              DATABASE LAYER                                     │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                │
│  ┌──────────────────────────────────────────────────────────────────────────┐ │
│  │                           DATABASE TRIGGERS                               │ │
│  ├──────────────────────────────────────────────────────────────────────────┤ │
│  │  trg_tasks_increment_version     → Auto-increment version on update      │ │
│  │  trg_projects_increment_version  → Auto-increment version on update      │ │
│  │  trg_tasks_validate_status_transition → Enforce workflow rules           │ │
│  │  trg_tasks_auto_complete_progress    → Sync progress with status         │ │
│  │  trg_prevent_orphan_tasks            → Block tasks in deleted projects   │ │
│  │  trg_prevent_project_delete_with_tasks → Block delete with active tasks  │ │
│  └──────────────────────────────────────────────────────────────────────────┘ │
│                                                                                │
│  ┌──────────────────────────────────────────────────────────────────────────┐ │
│  │                         STORED PROCEDURES                                 │ │
│  ├──────────────────────────────────────────────────────────────────────────┤ │
│  │  sp_assign_task_with_audit      → Atomic assignment + audit              │ │
│  │  sp_bulk_update_task_status     → Batch status update                    │ │
│  │  sp_transfer_project_ownership  → Manager transfer + audit               │ │
│  │  sp_archive_completed_tasks     → Batch archive old tasks                │ │
│  └──────────────────────────────────────────────────────────────────────────┘ │
│                                                                                │
│  ┌──────────────────────────────────────────────────────────────────────────┐ │
│  │                             DATA TABLES                                   │ │
│  ├──────────────────────────────────────────────────────────────────────────┤ │
│  │  tasks          (version, deleted_at)                                    │ │
│  │  projects       (version, deleted_at)                                    │ │
│  │  activity_logs  (polymorphic audit)                                      │ │
│  │  task_activity_log (task-specific audit)                                 │ │
│  │  transaction_logs  (transaction audit with before/after)                 │ │
│  └──────────────────────────────────────────────────────────────────────────┘ │
│                                                                                │
└────────────────────────────────────────────────────────────────────────────────┘
```

---

## Quick Reference: File Locations

| Component | Location |
|-----------|----------|
| **Traits** | |
| HasActivityLogs | `app/Concerns/HasActivityLogs.php` |
| HasOptimisticLocking | `app/Concerns/HasOptimisticLocking.php` |
| PreventsHardDeletes | `app/Concerns/PreventsHardDeletes.php` |
| UsesConcurrencyControl | `app/Concerns/UsesConcurrencyControl.php` |
| **Services** | |
| TransactionManager | `app/Services/TransactionManager.php` |
| LockManager | `app/Services/LockManager.php` |
| StoredProcedureService | `app/Services/StoredProcedureService.php` |
| **Models** | |
| TransactionLog | `app/Models/TransactionLog.php` |
| ActivityLog | `app/Models/ActivityLog.php` |
| TaskActivityLog | `app/Models/TaskActivityLog.php` |
| **Exceptions** | |
| StaleModelException | `app/Exceptions/StaleModelException.php` |
| LockAcquisitionException | `app/Exceptions/LockAcquisitionException.php` |
| **Migrations** | |
| Version columns | `database/migrations/2026_03_12_000001_add_version_columns_for_optimistic_locking.php` |
| Transaction logs table | `database/migrations/2026_03_12_000002_create_transaction_logs_table.php` |
| Database triggers | `database/migrations/2026_03_12_000003_create_database_triggers.php` |
| Stored procedures | `database/migrations/2026_03_12_000004_create_stored_procedures.php` |
| Activity logs table | `database/migrations/2026_03_10_030520_create_activity_logs_table.php` |
