# Authorization & Access Control System

## Overview

This document describes the production-grade Authorization & Access Control system
implementing RBAC, Team-Based Data Isolation, and Ownership-Based Modification Rights.

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────────┐
│                     REQUEST FLOW                                 │
├─────────────────────────────────────────────────────────────────┤
│  1. Route Middleware (EnsureRole, EnsureTeamAccess)             │
│     ↓ Early rejection for unauthorized roles/teams              │
├─────────────────────────────────────────────────────────────────┤
│  2. Controller Authorization (Policy checks via $this->authorize)│
│     ↓ Resource-specific authorization                           │
├─────────────────────────────────────────────────────────────────┤
│  3. Global Scopes (TeamScope, AssignedTaskScope)                │
│     ↓ Query-level filtering prevents data leaks                 │
├─────────────────────────────────────────────────────────────────┤
│  4. Model Events (prevent hard deletes)                         │
│     ↓ Audit protection                                          │
└─────────────────────────────────────────────────────────────────┘
```

## Role-Permission Matrix

| Resource | Action        | Admin | Manager           | Member            |
|----------|---------------|-------|-------------------|-------------------|
| Team     | viewAny       | ✅    | ✅ (own teams)    | ✅ (own teams)    |
| Team     | view          | ✅    | ✅ (own teams)    | ✅ (own teams)    |
| Team     | create        | ✅    | ❌                | ❌                |
| Team     | update        | ✅    | ❌                | ❌                |
| Team     | delete        | ❌    | ❌                | ❌                |
| Team     | addMember     | ✅    | ✅ (own teams)    | ❌                |
| Project  | viewAny       | ✅    | ✅ (team projects)| ✅ (team projects)|
| Project  | view          | ✅    | ✅ (team projects)| ✅ (team projects)|
| Project  | create        | ✅    | ❌                | ❌                |
| Project  | update        | ✅    | ✅ (if manager)   | ❌                |
| Project  | delete        | ❌    | ❌                | ❌                |
| Project  | assignManager | ✅    | ❌                | ❌                |
| Task     | viewAny       | ✅    | ✅ (project tasks)| ✅ (assigned)     |
| Task     | view          | ✅    | ✅ (project tasks)| ✅ (assigned)     |
| Task     | create        | ✅    | ✅ (own projects) | ❌                |
| Task     | update        | ✅    | ✅ (own projects) | ✅ (progress)     |
| Task     | delete        | ❌    | ❌                | ❌                |
| Task     | assign        | ✅    | ✅ (own projects) | ❌                |
| Comment  | create        | ✅    | ✅                | ✅ (own tasks)    |
| Comment  | update        | ✅    | ✅ (own)          | ✅ (own)          |
| Log      | viewAny       | ✅    | ❌                | ❌                |

## Security Pitfalls & Mitigations

### 1. IDOR Vulnerabilities
- **Risk**: `/projects/{id}` could allow guessing IDs
- **Mitigation**: Global scopes filter by team membership before ID lookup

### 2. Policy Bypass via Mass Assignment
- **Risk**: Updating `manager_id` directly on Project
- **Mitigation**: Explicit authorization in Policy + guarded attributes

### 3. N+1 Authorization Queries
- **Risk**: Checking team membership per-record
- **Mitigation**: Eager load relationships, cache role checks

### 4. Frontend-Only Authorization
- **Risk**: Hiding buttons without backend checks
- **Mitigation**: Backend policies are source of truth; frontend is UX only

### 5. Soft Delete Bypass
- **Risk**: Using `forceDelete()` accidentally
- **Mitigation**: Model event blocks `forceDeleting` event

## Performance Strategy

1. **Cache Role Lookups**: User roles rarely change; cache with user
2. **Eager Load Teams**: Always `with('teams')` for auth users
3. **Index Foreign Keys**: Ensure pivot tables have indexes
4. **Scope Early**: Apply Global Scopes before conditions

## Files Overview

- `app/Policies/*Policy.php` - Resource authorization
- `app/Http/Middleware/EnsureRole.php` - Role-based route protection
- `app/Http/Middleware/EnsureTeamAccess.php` - Team boundary enforcement
- `app/Models/Scopes/TeamScope.php` - Query-level team isolation
- `app/Providers/AuthServiceProvider.php` - Gate definitions
- `resources/js/composables/usePermissions.ts` - Frontend permission helpers
