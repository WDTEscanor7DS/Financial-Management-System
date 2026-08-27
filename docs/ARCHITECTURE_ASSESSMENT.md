# FMS — Phase 1: Existing System Inspection & Architecture Assessment

This document records what was actually found in the existing frontend
prototype (`/fms`) before any backend/database work began, and maps each
finding to a backend decision. Nothing below is generic boilerplate — every
table, enum, and route in the sections that follow was derived from the
files listed here.

## 1. Existing pages (11 + login)

`index.html` (role picker / login stand-in), `pages/dashboard.html`,
`budget.html`, `revenue.html`, `expenses.html`, `accounts-payable.html`,
`accounts-receivable.html`, `funds.html`, `procurement.html`, `assets.html`,
`reports.html`, `audit.html`.

## 2. Existing "authentication"

`js/auth.js` defines a hardcoded `ROLES` object (Administrator, Accountant,
College Administrator, Employee) with a `modules[]` allow-list and four
booleans (`canManageUsers`, `canApproveRequests`, `canEditFinancials`,
`canSubmitRequests`) per role. `loginAs(roleKey)` writes `{role, name,
loginAt}` straight into `localStorage` — there is no password, no server
round-trip, and any user can call `loginAs('Administrator')` from the
console. **This entire mechanism is replaced** (Sections 27–37 below); the
four roles and their four capability flags become the seed data for the
real `roles`/`permissions` tables in Section 6, so the permission
boundaries stay identical from the user's point of view.

## 3. Existing data layer (`js/data.js`)

A single `localStorage`-backed module with `STORE` keys per entity and
`getX()/createX()/updateX()/deleteX()` functions. This is the single most
important file for the migration: its function names, field names, and
cross-entity side effects (e.g. `createExpense` bumping
`budget.actualSpending`) are preserved exactly — only the storage engine
changes, from `localStorage` to Laravel/MySQL over `fetch()`.

Entities found, with exact fields as used in the seed data / forms:

| Store | Fields observed |
|---|---|
| `departments` | flat string list (no id) |
| `budgets` | `id, fiscalYear, department, category, allocated, actualSpending, status, createdAt` |
| `revenues` | `id, date, revenueType, description, department, payer, referenceNo, amount, paymentMethod, status` |
| `expenses` | `id, date, department, expenseCategory, description, vendor, referenceNo, amount, paymentMethod, status, budgetId` |
| `payables` | `id, vendor, invoiceNo, invoiceDate, dueDate, description, department, amount, amountPaid, balance, status` |
| `receivables` | `id, customer, referenceNo, description, invoiceDate, dueDate, amount, amountPaid, balance, status` |
| `funds` | `id, name, type, department, allocation, used, remaining, status` |
| `procurement` | `id, requester, department, requestType, description, quantity, estimatedCost, priority, dateSubmitted, status, reviewer, remarks` |
| `assets` | `id, assetName, category, serialNo, purchaseDate, purchaseCost, usefulLife, salvageValue, department, location, status` |
| `audit` | `id, user, role, action, module, recordId, timestamp, description, status` |
| `notifications` | `id, message, timestamp, read` |

## 4. Cross-module logic already implemented in JS (must be preserved server-side)

- `createExpense` → if `budgetId` set, increases `budget.actualSpending`
  and fires an 80%-utilization notification. Deleting/editing an expense
  reverses the old amount first (`updateExpense`/`deleteExpense`).
- `recordPayablePayment` / `recordReceivablePayment` → increment
  `amountPaid`, recompute `balance`, flip `status`, refuse if already
  `Paid`, refuse if payment exceeds balance (checked in both the JS data
  layer and the calling module's `handle*Submit`).
- `allocateFromFund` → refuses if amount exceeds `remaining`.
- `reviewProcurementRequest` → only sets `Approved`/`Rejected`;
  `advanceProcurementRequest` moves `Approved → Procurement Processing →
  Completed`. The UI (`procurement.js`) only renders the review/advance
  buttons when `role.canApproveRequests` is true — **but nothing stops a
  console call to `reviewProcurementRequest()` directly**, which is exactly
  the gap Sections 32–34 close.
- Every mutating call ends with `logAudit(action, module, recordId,
  description)`, reading `getCurrentUser()` for the actor. This becomes a
  server-side `AuditService` call inside each controller/service method
  (Section 17, 45), never a client-supplied field.

## 5. Duplication / inconsistency found

- `department` is stored as a free-text string on every transactional
  record, sourced from one shared `getDepartments()` string array — a
  textbook case for a real `departments` foreign key (Section 7).
- `payables.balance` / `receivables.balance` are stored values that the JS
  keeps in sync manually on every write. Moved server-side, these become
  values recomputed inside a DB transaction on every payment (Section 11–12,
  21), so they can never drift from the payment history.
- `funds.remaining` is likewise a derived value (`allocation - used`);
  kept as a generated/cached column recalculated inside the same
  transaction that inserts a `fund_allocations` row.

## 6. What moves from the browser into MySQL

Everything in the table in Section 3 except pure UI state. Two
`localStorage` keys are **allowed to remain client-side** because they are
not authoritative data: sidebar collapsed/open state and the currently
active report tab. Session/user identity, roles, and every financial
record move to the server per Section 53 of the brief.

## 7. Resulting decision

Build a Laravel + MySQL backend whose table names and fields are the
normalized, foreign-keyed version of the table above (see
`docs/DATABASE_ARCHITECTURE.md`), expose it as a JSON API under `/api/*`
plus session-based web auth under `/login` and `/logout`, and replace the
body of every function in `js/data.js` with a `fetch()` call to the
matching endpoint — keeping every function's name and return shape so the
eleven page-specific JS files (`budget.js`, `revenue.js`, ...) need only be
converted to `async`/`await`, not rewritten.
