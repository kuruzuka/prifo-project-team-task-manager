# Priflo

A collaborative project and task management platform that helps teams plan, track, and complete work efficiently with enterprise-grade concurrency control and comprehensive activity logging.

---

## Overview

Priflo is a full-stack web application designed for teams that need structured project management with robust access control. The system enables organizations to:

- **Organize work** into projects with assigned teams and managers
- **Track tasks** with statuses, priorities, progress percentages, and due dates
- **Collaborate** through task assignments and comments
- **Monitor activity** with comprehensive audit logging
- **Prevent conflicts** using optimistic locking and database-level constraints

Priflo implements a role-based permission system where Admins have full control, Managers oversee their projects, and Members work on assigned tasks within their teams.

---

## 🛡️ Security Features

This project places a high priority on data protection, robust authorization, and user experience during rate-limited events. The application incorporates industry-standard security practices specifically tailored for the Laravel and Inertia.js (Vue) ecosystem:

- **Robust Authorization:** Comprehensive implementation of Laravel Policies and Gates to prevent Insecure Direct Object References (IDOR). Access to Projects, Tasks, and Teams is strictly isolated based on exact user roles and team memberships.
- **Intelligent Rate Limiting:** Critical endpoints (login, registration, password resets, and content creation) are protected against spam and brute-force attacks. 
- **Global Rate-Limit UX:** An advanced frontend integration intercepts rate-limit triggers globally, providing users with persistent, context-aware countdown alerts that survive page refreshes and multi-tab sessions.
- **Strict Input Validation:** All state-modifying requests route through FormRequests, guaranteeing that unguarded mass-assignment vulnerabilities are mitigated.
- **Secure Headers & Sessions:** The application enforces a strict Content Security Policy (CSP) tailored for Vue, alongside HSTS, X-Frame-Options, and X-Content-Type-Options. Cookies are protected with HTTP-only and strict SameSite configurations.
- **Frontend XSS Protection:** Rendering of HTML via Vue (`v-html`) is strictly limited to server-generated, sanitized strings (e.g., SVG QR codes and pagination UI).
- **Data Exposure Prevention:** Production configurations guarantee that stack traces, environment variables, and debug data are never leaked to the client.

---

## Key Features

### Project Management
- Create and manage projects with start/end dates
- Assign teams to projects
- Track project status (Active, On Hold, Completed, Cancelled)
- View project progress based on task completion

### Task Management
- Create tasks within projects
- Set priority levels (Low, Medium, High, Critical)
- Track progress percentage (0-100%)
- Manage task status workflow (To Do → In Progress → In Review → Done)
- Set due dates

### Team Collaboration
- Organize users into teams
- Assign multiple users to tasks
- Add comments to tasks
- View team member workloads

### Role-Based Access Control
- **Developer** — Internal developer with full administrative access and exclusive access to system documentation.
- **Admin** — Full system access, can manage all projects and users.
- **Manager** — Manages assigned projects and creates tasks.
- **Member** — Works on assigned tasks within team projects.

### Activity Logging & Audit Trail
- Track all changes to projects and tasks
- Record who made changes and when
- Maintain transaction-level audit logs with before/after values

### Concurrency Control
- Optimistic locking prevents conflicting simultaneous edits
- Database triggers enforce business rules
- Stored procedures for atomic operations

### Dashboard Analytics
- View active task counts
- Monitor completed tasks
- Track project progress
- See recent task activity

### Authentication & Security
- User registration and login
- Two-factor authentication (2FA)
- Password reset functionality
- Email verification

---

## System Architecture

### Technology Stack

| Layer | Technology |
|-------|------------|
| **Frontend** | Vue 3.5 + TypeScript + Inertia.js 2.x |
| **Styling** | Tailwind CSS 4.x + shadcn-vue |
| **Backend** | Laravel 12 + PHP 8.4 |
| **Authentication** | Laravel Fortify |
| **Database** | MySQL 8.x |
| **Testing** | Pest 4.x + PHPUnit 12 |

### Request Flow

```
Browser → Vue 3 (SPA)
           ↓
       Inertia.js
           ↓
     Laravel Router
           ↓
       Middleware (Auth, CSRF)
           ↓
      Controllers
           ↓
  Policies (Authorization)
           ↓
    Eloquent Models
           ↓
   MySQL Database (Triggers, Stored Procedures)
```

### Key Architectural Decisions

1. **Inertia.js** — Provides SPA-like experience while using server-side routing, eliminating the need for a separate API layer
2. **Global Scopes** — Data is automatically filtered based on user role and team membership
3. **Database Triggers** — Critical business logic enforced at the database level for guaranteed consistency
4. **Optimistic Locking** — Version columns prevent concurrent edit conflicts

---

## Database Design

### Entity Relationship Overview

```
Users ←→ Teams (many-to-many via user_teams)
Users ←→ Roles (many-to-many via user_roles)
Users ←→ Tasks (many-to-many via task_assignments)
Users → Job Titles (many-to-one)

Teams ←→ Projects (many-to-many via team_projects)

Projects → Users (manager, many-to-one)
Projects → Project Statuses (many-to-one)
Projects → Tasks (one-to-many)

Tasks → Projects (many-to-one)
Tasks → Task Statuses (many-to-one)
Tasks → Users (creator, many-to-one)
Tasks → Comments (one-to-many)
Tasks → Task Activity Logs (one-to-many)

Comments → Users (author, many-to-one)
```

### Core Tables

| Table | Purpose |
|-------|---------|
| `users` | System users with authentication data |
| `teams` | Organizational units for grouping users |
| `projects` | Work containers with managers and dates |
| `tasks` | Individual work items with progress tracking |
| `task_assignments` | Links users to tasks |
| `comments` | Task discussion threads |
| `roles` | Permission levels (Admin, Manager, Member) |
| `activity_logs` | Polymorphic activity records |
| `task_activity_log` | Task-specific activity history |
| `transaction_logs` | Detailed audit trail with before/after values |

### Database Triggers

| Trigger | Purpose |
|---------|---------|
| `trg_tasks_increment_version` | Auto-increment version for optimistic locking |
| `trg_projects_increment_version` | Auto-increment project version |
| `trg_tasks_validate_status_transition` | Enforce valid workflow transitions |
| `trg_tasks_auto_complete_progress` | Set progress to 100% when status is Done |
| `trg_prevent_orphan_tasks` | Block task creation in deleted projects |
| `trg_prevent_project_delete_with_tasks` | Block project deletion if tasks exist |

### Stored Procedures

| Procedure | Purpose |
|-----------|---------|
| `sp_assign_task_with_audit` | Atomic task assignment with audit logging |
| `sp_bulk_update_task_status` | Batch status updates in transaction |
| `sp_transfer_project_ownership` | Safe manager transfer with audit trail |
| `sp_archive_completed_tasks` | Batch soft-delete old completed tasks |

---

## Authorization System

Authorization is implemented through Laravel Policies with role-based access control.

### Policies

| Policy | Protects |
|--------|----------|
| `ProjectPolicy` | Project CRUD operations |
| `TaskPolicy` | Task CRUD and status updates |
| `TeamPolicy` | Team membership management |
| `CommentPolicy` | Comment creation and editing |
| `UserPolicy` | User profile access |
| `ActivityLogPolicy` | Activity log viewing |

### Access Rules

**Admins:**
- Full access to all resources
- Can create projects
- Can manage all teams

**Managers:**
- Can view and edit projects they manage
- Can create and manage tasks in their projects
- Can assign users to tasks

**Members:**
- Can view projects their team is assigned to
- Can view and update tasks assigned to them
- Can add comments to accessible tasks

### Global Scopes

Data visibility is automatically filtered:

- `TeamProjectScope` — Users only see projects where their team is assigned
- `TaskAccessScope` — Users only see tasks they're assigned to or manage
- `TeamMembershipScope` — Users only see teams they belong to

---

## Activity Logging

The system maintains three types of activity logs:

### 1. Generic Activity Logs (`activity_logs`)

Polymorphic logs for any model using the `HasActivityLogs` trait.

**Models using this trait:**
- Project
- Task

### 2. Task Activity Logs (`task_activity_log`)

Task-specific events with structured metadata:

| Event Type | Description |
|------------|-------------|
| `task_created` | Task was created |
| `status_changed` | Status was updated |
| `priority_changed` | Priority was modified |
| `progress_updated` | Progress percentage changed |
| `assignee_added` | User was assigned |
| `assignee_removed` | User was unassigned |

### 3. Transaction Logs (`transaction_logs`)

Enterprise-level audit trail capturing:

- Transaction ID (UUID)
- Operation type and name
- Before/after values (JSON)
- Actor information (user, IP, user agent)
- Duration and status
- Error messages if failed

---

## Concurrency Control

### Optimistic Locking

Prevents lost updates when multiple users edit the same record simultaneously.

**How it works:**
1. Each record has a `version` column
2. When loading a record, the current version is sent to the frontend
3. When saving, the expected version is compared against the database
4. If versions differ, the save is rejected with a "stale data" error
5. Database triggers automatically increment the version on update

**Models using optimistic locking:**
- Project
- Task

### Pessimistic Locking

The `LockManager` service provides:

- Row-level locks (`SELECT FOR UPDATE`)
- Shared read locks
- Advisory locks for application-level coordination

### Transaction Management

The `TransactionManager` service ensures:

- Atomic operations with automatic rollback
- Logged transactions with full audit trail
- Deadlock handling and retry logic

---

## UI Overview

### Pages

| Page | Route | Description |
|------|-------|-------------|
| **Dashboard** | `/dashboard` | Statistics and recent activity |
| **My Tasks** | `/tasks` | Tasks assigned to current user |
| **Task Details** | `/tasks/{id}` | Full task view with comments |
| **Edit Task** | `/tasks/{id}/edit` | Task editing form |
| **Projects** | `/projects` | Project list with progress |
| **Project Details** | `/projects/{id}` | Project overview with tasks |
| **Teams** | `/teams` | Team list |
| **Team Details** | `/teams/{id}` | Team members and projects |
| **Settings** | `/settings/*` | Profile, password, 2FA |

### Component Library

Built with shadcn-vue components:
- Cards, Buttons, Inputs
- Dialogs, Dropdowns
- Tables, Badges
- Progress indicators
- Toast notifications

---

## Installation Guide

### Requirements

- PHP 8.4+
- Composer 2.x
- Node.js 20+
- npm 10+
- MySQL 8.x

### Installation Steps

```bash
# Clone the repository
git clone https://github.com/kuruzuka/prifo-project-team-task-manager.git
cd prifo-project-team-task-manager

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Create environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=priflo
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations and seed data
php artisan migrate --seed

# Build frontend assets
npm run build

# Start the development server
php artisan serve
```

### Development with Hot Reload

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev
```

Or use the combined command:

```bash
composer run dev
```

---

## Development Scripts

### PHP / Laravel

| Command | Description |
|---------|-------------|
| `php artisan serve` | Start development server |
| `php artisan migrate` | Run database migrations |
| `php artisan migrate:fresh --seed` | Reset database with seed data |
| `php artisan test` | Run test suite |
| `php artisan test --compact` | Run tests with compact output |
| `vendor/bin/pint` | Format PHP code |

### Node.js / Frontend

| Command | Description |
|---------|-------------|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Build production assets |
| `npm run lint` | Run ESLint |
| `npm run format` | Format code with Prettier |

### Combined

| Command | Description |
|---------|-------------|
| `composer run dev` | Start both Laravel and Vite servers |

---

## Folder Structure

```
priflo/
├── app/
│   ├── Concerns/           # Reusable traits
│   │   ├── HasActivityLogs.php
│   │   ├── HasOptimisticLocking.php
│   │   ├── PreventsHardDeletes.php
│   │   └── UsesConcurrencyControl.php
│   ├── Http/
│   │   ├── Controllers/    # Request handlers
│   │   ├── Middleware/     # Request filters
│   │   └── Requests/       # Form validation
│   ├── Models/             # Eloquent models
│   │   └── Scopes/         # Global query scopes
│   ├── Policies/           # Authorization policies
│   └── Services/           # Business logic services
│       ├── LockManager.php
│       ├── StoredProcedureService.php
│       └── TransactionManager.php
├── database/
│   ├── factories/          # Model factories for testing
│   ├── migrations/         # Database schema
│   └── seeders/            # Sample data
├── docs/                   # Technical documentation
├── resources/
│   ├── css/                # Stylesheets
│   ├── js/
│   │   ├── components/     # Vue components
│   │   │   └── ui/         # shadcn-vue components
│   │   ├── composables/    # Vue composables
│   │   ├── layouts/        # Page layouts
│   │   ├── pages/          # Inertia page components
│   │   └── types/          # TypeScript definitions
│   └── views/              # Blade templates
├── routes/
│   ├── web.php             # Web routes
│   ├── settings.php        # Settings routes
│   └── console.php         # Artisan commands
└── tests/
    ├── Feature/            # Integration tests
    └── Unit/               # Unit tests
```

---

## Security Design

### Authentication

- Laravel Fortify handles authentication
- Two-factor authentication (TOTP)
- Password hashing with bcrypt (12 rounds)
- Session-based authentication with CSRF protection

### Authorization

- Policy-based access control
- Role verification on all sensitive operations
- Global scopes prevent data leakage

### Data Protection

- Soft deletes preserve audit trail
- Hard deletes blocked via `PreventsHardDeletes` trait
- Optimistic locking prevents conflicting updates
- Database triggers enforce business rules

### Request Validation

- Form Request classes validate all input
- Type-safe frontend with TypeScript

### Database Security

- Prepared statements prevent SQL injection
- Foreign key constraints maintain referential integrity
- Indexes optimize query performance

---

## Future Improvements

Potential enhancements for future development:

- **Real-time Notifications** — Laravel Echo + WebSockets for instant updates
- **Advanced Reporting** — Export reports, charts, and analytics
- **File Attachments** — Upload documents and images to tasks
- **API Access** — RESTful API for third-party integrations
- **Mobile Application** — Native iOS/Android apps
- **Task Dependencies** — Link tasks with predecessor/successor relationships
- **Time Tracking** — Log time spent on tasks
- **Kanban Board** — Drag-and-drop task management view
- **Calendar Integration** — Sync due dates with external calendars
- **Email Notifications** — Automated alerts for assignments and deadlines

---

## Internal Developer Documentation

Priflo includes a built-in, web-based internal documentation system available at `/docs` for users with the **Developer** role. It provides a comprehensive guide to the system architecture, backend logic, and frontend component library.

### Access & Visibility

- **Role Required**: `Developer`. Users with this role inherit all Admin permissions.
- **URL**: `/docs` (protected by `EnsureRole` middleware).
- **Navigation**: The "Developer Docs" link is dynamically rendered in the sidebar and top navigation **only for Developer users** using the `usePermissions()` composable.
- **Unauthorized Access**: Non-developer users (including regular Admins) will encounter a **403 Forbidden** page if they attempt to access this route directly.

### Key Implementation Details

- **Architecture**: Modern Laravel 12 (PHP 8.4+) and Vue 3 (Composition API) single-page application powered by Inertia.js 2.0.
- **Routing**: Routes are logically separated across `routes/web.php` and `routes/settings.php`. The documentation is served via the `docs` named route.
- **Backend Core**:
    - **Controllers**: 12 Controllers handling HTTP requests and returning Inertia responses, delegating business logic to specialized Services or Actions.
    - **Models**: 13 Eloquent models (Project, Task, User, Team, Role, Comment, ActivityLog, TransactionLog, etc.) with advanced features like Optimistic Locking and Global Scopes.
    - **Middleware**: Custom security layers including `EnsureRole` (RBAC) and `EnsureTeamAccess`.
    - **Policies**: 6 Policies providing granular, instance-level authorization for all major entities (ProjectPolicy, TaskPolicy, etc.).
    - **Form Requests**: Extracted validation logic ensuring clean controllers.
    - **Services**: Singleton services manage cross-cutting concerns like `LockManager` (concurrency control) and `TransactionManager` (atomic operations with audit trails).
- **Frontend Core**:
    - **Structure**: Modular architecture with Inertia pages in `resources/js/pages/` and reusable Vue components in `resources/js/components/`.
    - **UI Components**: Built using `shadcn-vue` primitives (Card, Tabs, Collapsible, Separator, Badge, etc.) for a consistent, accessible, and responsive experience.
    - **Styling**: Utility-first design using Tailwind CSS 4.

---

## Documentation

Additional technical documentation is available in the `docs/` folder:

- [Authorization System](docs/AUTHORIZATION.md)
- [Database Reliability](docs/DATABASE_RELIABILITY.md)
- [Database Behavior & Concurrency](docs/DATABASE_BEHAVIOR_TECHNICAL_DOCUMENTATION.md)
- [Full Security Checks and Features Documentation](docs/SECURITY_CHECKS_AND_TEST.md)

---

## License

This project is proprietary software. All rights reserved.

---

## Contributing

Contributions are welcome. Please ensure:

1. Code follows existing conventions
2. Tests pass: `php artisan test`
3. Code is formatted: `vendor/bin/pint`
4. TypeScript compiles without errors