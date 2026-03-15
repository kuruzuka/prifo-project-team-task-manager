# DATABASE ASSIGNMENT TECHNICAL DOCUMENTATION

## PART 1: SQL QUERY TRANSLATIONS (FOR SCREENSHOTS)

### SECTION 1 — CREATE TABLE

**Screenshot 1: Projects Table**
- **File:** `database/migrations/2026_01_24_122503_create_projects_table.php`
- **Lines:** 14–23
- **Equivalent SQL:**
```sql
CREATE TABLE projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(255) NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (status_id) REFERENCES project_statuses(id) ON DELETE RESTRICT
);
```

**Screenshot 2: Tasks Table**
- **File:** `database/migrations/2026_01_24_122504_create_tasks_table.php`
- **Lines:** 14–26
- **Equivalent SQL:**
```sql
CREATE TABLE tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority VARCHAR(255) DEFAULT 'medium' NOT NULL,
    progress BIGINT UNSIGNED DEFAULT 0 NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (project_id, title),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    FOREIGN KEY (status_id) REFERENCES task_statuses(id) ON DELETE RESTRICT
);
```

---

### SECTION 2 — ALTER TABLE

**Screenshot 1: Adding Project Manager**
- **File:** `database/migrations/2026_03_10_155711_add_manager_id_to_projects_table.php`
- **Lines:** 14–20
- **Equivalent SQL:**
```sql
ALTER TABLE projects
ADD COLUMN manager_id BIGINT UNSIGNED NULL AFTER status_id,
ADD CONSTRAINT projects_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;
```

**Screenshot 2: Adding Job Titles to Users**
- **File:** `database/migrations/2026_03_10_160107_add_job_title_id_to_users_table.php`
- **Lines:** 14–20
- **Equivalent SQL:**
```sql
ALTER TABLE users
ADD COLUMN job_title_id BIGINT UNSIGNED NULL AFTER email,
ADD CONSTRAINT users_job_title_id_foreign FOREIGN KEY (job_title_id) REFERENCES job_titles(id) ON DELETE SET NULL;
```

---

### SECTION 3 — INSERT / UPDATE / DELETE

**Screenshot 1: Insert (Comment Creation)**
- **File:** `app/Http/Controllers/CommentController.php`
- **Lines:** 28–31
- **Equivalent SQL:**
```sql
INSERT INTO comments (task_id, user_id, comment_text, created_at, updated_at) 
VALUES (?, ?, ?, NOW(), NOW());
```

**Screenshot 2: Update (Comment Editing)**
- **File:** `app/Http/Controllers/CommentController.php`
- **Lines:** 58–60
- **Equivalent SQL:**
```sql
UPDATE comments 
SET comment_text = ?, updated_at = NOW() 
WHERE id = ?;
```

**Screenshot 3: Delete (User Profile Removal)**
- **File:** `app/Http/Controllers/Settings/ProfileController.php`
- **Lines:** 53
- **Equivalent SQL:**
```sql
DELETE FROM users WHERE id = ?;
```

---

### SECTION 4 — SELECT QUERIES (REPORTS & JOINS)

**Screenshot 1: Project Overview (Joins, Aggregates, Subqueries)**
- **File:** `app/Http/Controllers/ProjectController.php`
- **Lines:** 115–149
- **Equivalent SQL:**
```sql
SELECT 
    projects.id, projects.name, projects.description, projects.status_id, projects.manager_id, projects.start_date, projects.end_date,
    (SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id AND tasks.deleted_at IS NULL) as tasks_count,
    (SELECT AVG(progress) FROM tasks WHERE tasks.project_id = projects.id AND tasks.deleted_at IS NULL) as tasks_avg_progress,
    (SELECT COUNT(DISTINCT user_teams.user_id) FROM team_projects JOIN user_teams ON team_projects.team_id = user_teams.team_id WHERE team_projects.project_id = projects.id) as team_members_count
FROM projects 
WHERE (projects.manager_id = ? OR EXISTS (...)) 
AND projects.deleted_at IS NULL 
ORDER BY projects.created_at DESC;
```

**Screenshot 2: Dashboard Stats (Conditional Joins)**
- **File:** `app/Http/Controllers/DashboardRouter.php`
- **Lines:** 25–35
- **Equivalent SQL:**
```sql
SELECT COUNT(*) FROM projects 
WHERE (projects.manager_id = ? OR EXISTS (
    SELECT * FROM team_projects 
    INNER JOIN user_teams ON team_projects.team_id = user_teams.team_id 
    WHERE team_projects.project_id = projects.id AND user_teams.user_id = ?
));
```

---

## PART 2: SYSTEM ARCHITECTURE & INTEGRITY

### SECTION 5 — DATABASE DEFINITION
In this project, the database is defined via configuration files. It is created externally (manually) or automatically generated if using SQLite.

**1. Primary Environment Configuration**
- **File:** `.env.example` (Lines 17–22)
- **Code:**
```env
DB_CONNECTION=sqlite
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

**2. Database Connection Logic**
- **File:** `config/database.php` (Lines 47–52)
- **Code:**
```php
'mysql' => [
    'driver' => 'mysql',
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'root'),
    // ...
],
```

---

### SECTION 6 — REFERENTIAL INTEGRITY
The system enforces data integrity through **Foreign Key Constraints** in the database schema and **Relationship Methods** in the application logic.

**1. Database Schema Enforcement**
- **File:** `database/migrations/2026_01_24_122503_create_projects_table.php` (Line 18)
- **Code:**
```php
$table->foreignId('status_id')->constrained('project_statuses')->restrictOnDelete();
```
*Effect: Prevents deletion of a status that is currently assigned to a project.*

**2. Application Logic Enforcement**
- **File:** `app/Http/Controllers/TeamController.php` (Line 267)
- **Code:**
```php
$team->members()->attach($user->id);
```
*Effect: Maintains the many-to-many relationship by inserting valid foreign keys into the pivot table.*
