# Security Checks & Implementations Log

This document serves as a chronological ledger of security audits, implementations, and best-practice enforcements applied to the Priflo (Laravel 12 + Inertia + Vue) project.

---

## 1. Initial Security Posture & Vulnerability Analysis
**Objective:** Assess baseline security spanning Authentication, Input Validation, and Dependencies.

*   **Dependency Audit:** Conducted `composer audit` and `npm audit`. Discovered a high-severity vulnerability in a frontend dependency (`flatted`). Addressed via `npm audit fix`.
*   **Mass Assignment Protection:** Verified that while Eloquent models (e.g., `User`) contain sensitive fields in their `$fillable` arrays (`password`, `job_title_id`), controllers completely avoid `$request->all()`. FormRequests (`ProfileUpdateRequest`, `TaskStoreRequest`) enforce rigorous field-mapping.
*   **CSRF Protection:** Verified Laravel's global CSRF token validation via `HandleInertiaRequests`. Noted that CSRF is conditionally disabled if `APP_ENV=testing`, necessitating strict environment management in production.

## 2. HTTP Security Headers Hardening
**Objective:** Protect against Cross-Site Scripting (XSS), Clickjacking, and packet sniffing.

*   **Implementation:** Created a global middleware at `app/Http/Middleware/SecureHeaders.php`.
*   **Configuration:** 
    *   `X-Frame-Options: SAMEORIGIN`
    *   `X-Content-Type-Options: nosniff`
    *   `Strict-Transport-Security: max-age=31536000; includeSubDomains`
    *   `Content-Security-Policy`: Set a Vue/Vite-compatible baseline (`default-src 'self'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';`).
*   **Registration:** Appended safely to the `web` middleware group inside `bootstrap/app.php`.

## 3. Backend Rate Limiting Implementation
**Objective:** Prevent credential stuffing, mass-registration, and database spamming.

*   **Implementation:** Configured dynamic rate limiters in `app/Providers/AppServiceProvider.php`.
    *   `throttle:registration`: 3 attempts/min by IP.
    *   `throttle:password-reset`: 3 attempts/min by IP.
    *   `throttle:creation` (Projects, Tasks, Comments): 10 actions/min by User ID.
*   **Fortify Integration:** Used the `$app->booted()` lifecycle hook to attach custom throttles dynamically to Fortify vendor routes (`register.store`, `password.email`, `password.update`) without altering vendor files.
*   **Verification:** Confirmed that Laravel Fortify’s built-in login limiter correctly combines email and IP address (`Str::lower($email).'|'.$ip`) to defeat localized credential stuffing.

## 4. Global Rate-Limit User Experience (Frontend)
**Objective:** Replace standard 429 error modals with an elegant, persistent countdown UI.

*   **Composable (`useRateLimit.ts`):** Created a centralized state manager tracking rate limit expiration via `localStorage`. This ensures countdowns survive page reloads.
*   **Inertia Interceptor:** Hooked into `router.on('exception')` and `router.on('invalid')` to catch HTTP 429 responses globally. Parsed the `Retry-After` header seamlessly for both Axios and Fetch implementations.
*   **UI Component (`RateLimitAlert.vue`):** Implemented a context-aware `shadcn-vue` alert that dynamically translates contexts (e.g., login vs. content creation) into human-readable warnings ("Too many failed login attempts" vs "Too many actions performed").
*   **Audit Findings & Iterations:** 
    *   *Multi-tab Sync:* Recommended upgrading the composable to listen to the window `storage` event so that a 429 triggered in Tab A instantly displays the alert in Tab B.
    *   *Accessibility (ARIA):* Recommended separating the ticking timer from screen readers using `aria-hidden` and `sr-only` classes to prevent screen readers from continuously announcing every second of the countdown.

## 5. Authorization & IDOR Penetration Testing
**Objective:** Ensure isolated data access and robust Team/Project boundaries.

*   **Mechanism:** Deep read-only analysis of `TaskController`, `ProjectController`, and `TeamController` alongside `TaskPolicy` and `ProjectPolicy`.
*   **Findings & Identified Weaknesses:**
    *   **Task Access Leakage (`forUser` endpoints):** Identified that stripping `TaskAccessScope` to view a team member's tasks could accidentally expose tasks belonging to *other* teams that the viewer is not a part of.
    *   **Horizontal Privilege Escalation Risk:** In `ProjectController@updateTeams`, the validation rule `'exists:teams,id'` allows a Project Manager to attach *any* valid team to a project, even teams they do not belong to.
    *   **IDOR Prevention:** Verified that `Gate::authorize()` successfully precedes database transaction logic universally (e.g., `removeAssignee`, `updateProgress`), effectively mitigating IDOR attempts as long as Policies remain watertight.
*   **Recommendation:** Avoid raw `withoutGlobalScopes()->findOrFail()` in controllers; prefer context-aware eloquent querying (`$user->accessibleTasks()->findOrFail()`) to provide defense-in-depth beneath the Policy layer.

## 6. Sensitive Data Exposure Mitigation
**Objective:** Verify that no credentials, API keys, or stack traces can leak to the public.

*   **Environment Validation:** Confirmed that all configurations (`database.php`, `services.php`, `filesystems.php`) safely utilize the `env()` helper. No hardcoded secrets exist in the repository.
*   **Frontend XSS Checks:** Audited Vue source code. No `console.log()` statements leak backend responses. Found that `v-html` is strictly utilized for safe server-generated data (Fortify SVG QR codes and native pagination).
*   **Exception Handling:** Verified `bootstrap/app.php` overrides 404 and 403 exceptions to render clean Inertia error pages instead of exposing Laravel routing logic.
*   **Maintenance Mandate:** `APP_DEBUG=true` is currently set for local development. CI/CD pipelines **must** enforce `APP_DEBUG=false` in production to prevent the Ignition debugger from leaking `.env` variables upon a fatal application error.

---
*Document maintained by Security & Engineering Teams.*