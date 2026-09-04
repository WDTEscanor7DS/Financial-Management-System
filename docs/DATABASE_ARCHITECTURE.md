# FMS — Database Architecture

## A. Entity-Relationship Diagram

```
departments 1───* users *───1 roles *───* permissions
     │                                  (via permission_role)
     │
     ├──1───* budgets ──1───* expenses *───1 departments
     │                          │
     │                          └──*───1 budgets (nullable link)
     │
     ├──1───* revenues
     ├──1───* funds ──1───* fund_allocations
     ├──1───* procurement_requests *───1 users (requester_id, reviewer_id)
     └──1───* assets

accounts_payable   1───* ap_payments
accounts_receivable 1───* ar_payments

users 1───* audit_logs
users 1───* notifications (nullable user_id = broadcast)
```

Cardinality notes:

- `departments → users/budgets/revenues/expenses/funds/procurement_requests/assets`
  are all **1-to-many**: one department has many of each; every one of
  those child rows belongs to exactly one department (`restrictOnDelete`
  — a department in use cannot be deleted out from under its records).
- `roles ↔ permissions` is **many-to-many** through `permission_role`
  (a Role has many Permissions; a Permission can belong to more than one
  Role — e.g. `view_dashboard` is granted to all four roles).
- `budgets → expenses` is **1-to-many, nullable**: an expense may or may
  not be linked to a budget (`budget_id` nullable, matching the existing
  frontend's optional "Linked Budget" field).
- `accounts_payable → ap_payments` and `accounts_receivable → ar_payments`
  are **1-to-many**: one invoice/receivable, many partial payments.
- `funds → fund_allocations` is **1-to-many**: one fund, an append-only
  ledger of every allocation made against it.
- `procurement_requests → users` is **two separate many-to-1**
  relationships on the same table (`requester_id`, `reviewer_id`), which is
  why the model exposes them as two distinct relations rather than one.

## B. Table Reference

| Table | Purpose | Notable constraints |
|---|---|---|
| `departments` | The college's six departments | `name` unique |
| `roles` | 4 seeded roles | `slug` unique |
| `permissions` | Granular ability slugs | `slug` unique, `group` indexed |
| `permission_role` | RBAC join table | composite PK `(role_id, permission_id)` |
| `users` | Authenticated accounts | `email` unique, soft-deletes, `status` enum |
| `budgets` | One row per dept/fiscal-year/category allocation | unique `(department_id, fiscal_year, category)`; CHECK `allocated >= 0` |
| `revenues` | Revenue transactions | CHECK `amount >= 0` |
| `expenses` | Expense transactions, optional budget link | CHECK `amount >= 0` |
| `accounts_payable` | Vendor invoices | unique `(vendor, invoice_no)`; CHECK `due_date >= invoice_date` |
| `ap_payments` | Payment history against an invoice | CHECK `amount > 0` |
| `accounts_receivable` | Amounts owed to the college | CHECK `due_date >= invoice_date` |
| `ar_payments` | Collection history | CHECK `amount > 0` |
| `funds` | Named funds with an allocation ceiling | CHECK `used <= allocation` |
| `fund_allocations` | Append-only allocation ledger | CHECK `amount > 0` |
| `procurement_requests` | Purchase/reimbursement/financial requests | enum `status` restricted to the 5 legal states |
| `assets` | Fixed assets | CHECK `salvage_value <= purchase_cost`, `useful_life > 0` |
| `audit_logs` | Immutable security/financial event log | no `updated_at`, append-only at the model layer |
| `notifications` | In-app notices, per-user or broadcast | `user_id` nullable |

Every monetary column is `DECIMAL(15,2)` — never `FLOAT`/`DOUBLE` — per
Section 9/52. `InnoDB` is Laravel's MySQL default engine and is used
throughout for foreign keys, row-level locking, and transactions
(Section 24).

## C. Why some values are cached, not just computed

`budgets.actual_spending`, `accounts_payable.amount_paid`/`balance`,
`accounts_receivable.amount_paid`/`balance`, and `funds.used` are stored
columns, not virtual/generated ones. Each is a sum over a corresponding
append-only ledger table (`expenses`, `ap_payments`, `ar_payments`,
`fund_allocations`) and is recalculated **only** inside the DB transaction
that inserts a new ledger row (see `app/Services/*Service.php`). This
keeps read queries (dashboard, tables, reports) cheap — no `SUM()` over
potentially large history tables on every page load — while the write
path guarantees the cache can never drift, because it is never updated
from anywhere else.

`asset` depreciation figures (annual/accumulated/book value) are the
opposite case: fully deterministic from `purchase_cost`, `salvage_value`,
`useful_life`, and `purchase_date`, so they are computed on read
(`Asset::annualDepreciation()` etc.) and never stored at all.

## D. Indexing Strategy

| Index | Reason |
|---|---|
| `users(role_id, status)` | every permission check filters active users by role |
| `budgets(department_id, fiscal_year)` | Budget Planning's own department/year filters |
| `revenues/expenses(department_id, date)` | date-range + department filtering on every list/report |
| `expenses(budget_id)` | reconciling actual_spending, and the "Linked Budget" filter |
| `accounts_payable(department_id, status)`, `(due_date)` | AP list filters + aging bucket queries |
| `accounts_receivable(due_date)`, `(reference_no)` | AR aging + search |
| `procurement_requests(department_id, status)`, `(requester_id)` | tab filters + "my requests" scoping for Employees |
| `assets(department_id, category)` | Asset module filters |
| `audit_logs(user_id)`, `(module)`, `(created_at)` | Audit Trail's search/filter/sort |
| `notifications(user_id, read)` | unread-count badge on every page load |

Composite indexes are ordered with the equality-filtered column first
(e.g. `department_id`) and the range/sort column second (`date`,
`created_at`), matching how MySQL uses a composite B-tree index.
Columns that are *always* looked up by exact ID (every foreign key) get
their index for free from `->constrained()`, so they are not repeated in
the table above.

## E. ACID / Transaction Strategy

Every financial write that touches more than one row goes through
`DB::transaction()` inside a Service class (never directly in a
Controller), so a mid-operation failure rolls back cleanly:

| Operation | What's inside the transaction |
|---|---|
| `ExpenseService::create/update/delete` | expense row + budget's `actual_spending` delta |
| `PayableService::recordPayment` | `ap_payments` row + payable's `amount_paid`/`balance`/`status` |
| `ReceivableService::recordPayment` | `ar_payments` row + receivable's `amount_paid`/`balance`/`status` |
| `FundService::allocate` | `fund_allocations` row + fund's `used` |
| `ProcurementService::review/advance` | status transition + reviewer/timestamp |

**Isolation** against concurrent writers is handled with
`lockForUpdate()` on the row being modified (Budget in
`ExpenseService::applyDelta`, `AccountsPayable`/`AccountsReceivable` in
their payment methods, `Fund` in `FundService::allocate`). This is what
prevents the exact race condition described in Section 51 of the brief:
two Accountants allocating against the same ₱100,000 remaining fund
balance at the same moment. The second transaction's `SELECT ... FOR
UPDATE` blocks until the first commits, then re-reads the now-current
`used` value before validating the new allocation — so the second request
is correctly rejected instead of both silently succeeding.

## F. Backup Architecture

For the current local XAMPP/MySQL environment:

- **Daily automated backup**: `mysqldump --single-transaction
  fms_database > backups/fms_$(date +%F).sql`, scheduled via Windows Task
  Scheduler / cron, retained for 14 days.
- **Weekly full backup**: same command, retained for 8 weeks, stored on a
  separate physical disk or cloud bucket (not the same machine as the
  database).
- **Backup verification**: restore the latest dump into a scratch
  database monthly and run `php artisan migrate:status` plus a row-count
  spot check against production, so a corrupted dump is caught before it's
  needed for real.
- **Recovery Point Objective (RPO)**: up to 24 hours of data loss in the
  worst case (time since the last daily backup), given a single local
  MySQL instance with no replication.
- **Recovery Time Objective (RTO)**: the time to reinstall
  XAMPP/Laravel on a new machine, restore the latest `.sql` dump, and run
  `php artisan migrate --pretend` to confirm schema parity — realistically
  1–2 hours for this project's scale, not an automated failover.

This project explicitly does **not** claim high availability: a single
XAMPP instance on one machine is a single point of failure, and Section 26
of the brief is clear that pretending otherwise would be dishonest. See
`docs/SECURITY_ARCHITECTURE.md` "High Availability Path" for what would
actually need to change to remove that single point of failure.

## G. Scalability / Archiving Notes

- Every list endpoint already filters by department/status/date rather
  than returning entire tables, so query cost grows with active records,
  not total history.
- `audit_logs` and `ap_payments`/`ar_payments`/`fund_allocations` are
  append-only and will be the fastest-growing tables. If/when this
  matters in practice, the recommended next step is a yearly archive job
  that moves rows older than N years into an identically-structured
  `audit_logs_archive` table (or cold storage), rather than deleting them
  — audit history in particular should never simply be dropped.
- `AuditLogController::index()` already paginates (100/page) rather than
  returning the whole table, so the frontend's Audit page stays responsive
  regardless of table size.
