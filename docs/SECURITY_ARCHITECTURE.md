# FMS — Security Architecture

## A. Authentication Flow

```
Browser (public/app/index.html or any /pages/*.html)
   │  fetch('/api/me')  -- on every page load
   ▼
401? ──yes──▶ redirect to /login (real Blade view, not the old
   │                              localStorage-based role picker)
   no
   ▼
Continue rendering with the user/role/permissions from /api/me
```

```
POST /login  { email, password, remember }
   ▼
LoginRequest::authenticate()
   ├─ ensureIsNotRateLimited()   -- 5 attempts / email+IP / minute
   ├─ Auth::attempt()            -- Hash::check() under the hood
   │    fail ─▶ RateLimiter::hit() + audit "Login Failed" + generic
   │            "Invalid email or password." (never reveals which part
   │            was wrong -- Section 28)
   ├─ isActive() check            -- Inactive/Suspended rejected even
   │                                 with a correct password
   └─ success ─▶ session()->regenerate()  -- prevents session fixation
                  last_login_at updated
                  audit "Login" recorded
                  redirect to intended page (usually /app/pages/dashboard.html)
```

## B. Authorization Flow

```
Any /api/* route
   ▼
auth:sanctum  -- 401 if no valid session
   ▼
account.active  -- 403 + force logout if status changed to Inactive/Suspended
                    mid-session (session is DB-backed, so this is checked
                    on literally the next request, not just next login)
   ▼
permission:<slug>  -- 403 if the user's Role does not have this Permission
   ▼
Controller ─▶ Service (business rule checks: e.g. "does this exceed the
              remaining balance", "is this a legal status transition")
   ▼
DB::transaction(...)  -- the actual mutation, row-locked where concurrent
                          writers could otherwise corrupt a balance
   ▼
AuditService::log(...)  -- recorded from inside the service, never trusted
                            from the client
```

Every layer above is independent of the frontend's own role-based
UI hiding (`js/auth.js` `canAccessModule()`): removing or bypassing that
JavaScript client-side changes nothing about what the API will accept.

## C. RBAC Design

Four roles (`roles` table), a flat list of permission slugs
(`permissions` table), and a many-to-many join (`permission_role`) --
see `database/seeders/RolePermissionSeeder.php` for the exact
role → permission mapping, which was derived directly from the
`canManageUsers`/`canApproveRequests`/`canEditFinancials`/
`canSubmitRequests` booleans already present in the old frontend-only
`ROLES` object (see `docs/ARCHITECTURE_ASSESSMENT.md`).

`User::can($slug)` (in `app/Models/User.php`) is the single place this is
resolved, so both `$request->user()->can('create_revenue')` in a
controller and `->middleware('permission:create_revenue')` on a route
ask the exact same question the exact same way -- there is no second,
divergent permission-checking code path to accidentally get out of sync.

## D. Password Security

- Hashing: `Hash::make()` (bcrypt) on write, `Hash::check()` /
  `Auth::attempt()` on read. The `password` cast on `User` (`'password' =>
  'hashed'`) additionally guarantees any future direct `$user->password =
  $plain; $user->save();` still gets hashed rather than accidentally
  storing plaintext.
- Never logged, audited, or serialized: `User::$hidden` excludes
  `password`/`remember_token` from every JSON response; `AuditService`
  never receives a password value as an argument anywhere in the
  codebase.
- Strength rule: `Password::min(10)->mixedCase()->numbers()` on every
  password-setting endpoint (registration is admin-only here, self
  password-change, and reset).
- Reset flow: Laravel's built-in `password_reset_tokens` broker --
  tokens are hashed at rest, single-use (the row is deleted on successful
  reset), and expire per `config('auth.passwords.users.expire')` (60
  minutes). No controller in this project ever generates, stores, or
  emails a raw token or a raw new password (Section 36).

## E. Session Security

- `SESSION_DRIVER=database` (see `.env.example`) — sessions live in MySQL,
  which is what lets `EnsureAccountActive` immediately kill a disabled
  user's session rather than waiting for it to expire naturally.
- `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` — cookie is
  inaccessible to JavaScript and not sent cross-site.
- `SESSION_SECURE_COOKIE` — `false` for local XAMPP/HTTP development,
  **must** be set `true` once served over HTTPS.
- Session ID regenerated on every successful login (`LoginController`);
  session invalidated and CSRF token rotated on every logout, so the
  browser Back button cannot replay an authenticated page afterward
  (Section 62).

## F. CSRF, SQL Injection, XSS

- **CSRF**: Laravel's `VerifyCsrfToken` middleware (enabled by default on
  the `web` group) protects `/login`, `/logout`, `/password`, and the
  reset-password routes. The `/api/*` routes are session-cookie
  authenticated via Sanctum's stateful-domain support, which also
  requires the `X-XSRF-TOKEN` header the frontend's `fetch()` calls read
  from the `XSRF-TOKEN` cookie automatically once
  `GET /sanctum/csrf-cookie` has been called on page load (see
  `js/api-client.js`).
- **SQL Injection**: every query in this project goes through Eloquent
  (`Model::where(...)`, `Model::create([...])`) or the query builder with
  bound parameters. There is no raw `DB::statement()`/`DB::select()` with
  interpolated user input anywhere in `app/`.
- **XSS**: the existing frontend already escapes everything it renders
  via its own `escapeHtml()` helper in `js/app.js` before interpolating
  API data into the DOM (descriptions, vendor names, remarks, etc.); that
  behavior is unchanged by this backend work. Blade views additionally
  use `{{ }}` (auto-escaping) rather than `{!! !!}` throughout
  `resources/views/`.

## G. Financial Record Deletion Policy

Per Section 44, the current implementation still exposes a literal
`DELETE` for each module (matching the existing frontend's Delete
buttons) rather than a void/reverse workflow, because introducing a full
reversal ledger was judged out of scope for this pass without discussion.
**Recommended next step, not yet implemented**: once a
Revenue/Expense/Payable/Receivable/Fund-allocation/Procurement/Asset
record has moved out of a "just created, not yet reconciled elsewhere"
state, replace `destroy()` with a `void()` action that flips a `voided_at`
timestamp and writes a compensating audit entry, rather than physically
removing the row. Flagging this explicitly rather than quietly shipping
hard deletes and calling the section satisfied.

## H. High Availability Path (not implemented, explained per Section 26)

Current state: one XAMPP machine running Apache + PHP + MySQL together —
a single point of failure by construction, and this document does not
pretend otherwise.

To remove that single point of failure in a real deployment:

```
Users
  │
  ▼
Load Balancer  (health-checks multiple app servers)
  │
  ▼
2+ Laravel app servers (stateless -- sessions live in MySQL/Redis,
  │                       not in-process, so any server can handle any
  │                       request)
  ▼
Managed MySQL (primary + read replica, automated failover)
  │
  ▼
Off-server backup storage (S3-compatible or similar)
```

None of this is built here — it would require real infrastructure
(multiple servers, a managed database, a load balancer) that a local
student capstone environment does not have, and building fake
"high-availability" code against a single local MySQL instance would be
misleading rather than useful.
