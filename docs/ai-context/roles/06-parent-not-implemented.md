# 06 — Parent / Guardian (NOT IMPLEMENTED)

> Code-grounded reference. Verify against source before acting.

**Role siblings:** [01 — Super Admin](./01-super-admin.md) · [02 — School Admin](./02-school-admin.md) · [03 — Branch Admin](./03-branch-admin.md) · [04 — Teacher](./04-teacher.md) · [05 — Student](./05-student.md)

**Status: Not implemented / future.** Everything on this page describes the *absence* of a parent role and the implications for anyone (human or AI) who assumes one exists.

---

## Verdict: there is NO parent/guardian role in the codebase

An exhaustive search found **no** parent or guardian user role, guard, model, table, controller, route, or view. The V2 platform has exactly **five** roles, and parents are not among them:

| Checked | Result | Source |
|---------|--------|--------|
| Auth guards | 5 guards only: `v2_super_admin`, `v2_school_admin`, `v2_branch_admin`, `v2_teacher`, `v2_student`. **No `v2_parent`/`v2_guardian`.** | `config/auth.php` (guards + providers) |
| Models | `app/Models/V2/` has SuperAdmin, SchoolAdmin, BranchAdmin, Teacher, Student. **No `Parent.php`/`Guardian.php`.** | `app/Models/V2/` |
| Migrations / tables | **No `v2_parents`/`v2_guardians`** (or any parent/guardian user table). | `database/migrations/` |
| Routes | `routes/v2.php` prefixes: `super-admin`, `school`, `branch`, `teacher`, `student`, plus `temp`/`learn`. **No `/parent` or `/guardian` prefix.** | `routes/v2.php` |
| Controllers | **No parent/guardian controllers** anywhere under `app/Http/Controllers/`. | `app/Http/Controllers/` |
| Views | **No parent/guardian views.** | `resources/views/` |

The brief that commissioned this documentation mentions parents "if applicable" — **they are not applicable in the current code.**

## "parent" appears in code — but never as a user role

The word *parent* occurs legitimately in unrelated, mundane contexts. None of these is a parent user:

- **Tree-structure columns** — `parent_id` on the topics hierarchy (`database/migrations/2026_05_02_000013_create_topics_table.php`) and on the Notes tree (notebooks / sections / pages nest via a parent reference). These model *hierarchy*, not guardianship.
- **PHP `parent::`** — normal class-inheritance calls (e.g. controllers calling `parent::__construct`).
- **DOM / UI `parent`** — parent elements/components in Blade/React/Alpine markup.
- **Filesystem** — parent directories.

If an AI agent greps for "parent" it will hit these; **do not mistake any of them for a parent/guardian role.**

## The one place a "parent" concept surfaces — and why it is misleading

The **branch-admin student progress report** is written in a deliberately **"parent-friendly"** tone, but it is a **branch-admin feature, not a parent login**:

- `app/Services/V2/ReportService.php` builds the narrative with an OpenAI prompt that explicitly asks for *"parent-friendly"* language (system prompt line 87; the user prompt asks for a "parent-friendly" summary, line 76).
- The report is **generated and downloaded by the branch admin** (`BranchAdmin\ReportController@generate` / `@pdf`, `routes/v2.php:255-256`), scoped to the admin's `branch_id`. There is no parent recipient, no parent auth, and no delivery-to-parent mechanism (no email/portal). See [03 — Branch Admin](./03-branch-admin.md) and [module 11 — Reports (current system)](../modules/11-reports-current-system.md).

**Implication:** "parent-friendly wording" is a *style* choice on an admin-delivered PDF, **not** evidence of a parent role. A branch admin presumably shares the PDF with a parent out-of-band (email/print/meeting), but the platform models none of that.

## Implications for AI agents

- **Do not assume a parent identity, guard, session, or `parent_id`-on-student exists.** There is no `v2_parents` table and no student→parent link. Any query that reaches for one will fail.
- **Do not build parent-facing features against imaginary infrastructure.** A parent portal is **greenfield** — it would need a new guard, provider, model, table, controller namespace, routes, views, and (critically) a **tenancy + child-linkage scope** consistent with the existing global-scope pattern (see [module 01 — Roles, Permissions & Tenancy](../modules/01-roles-permissions-tenancy.md)).
- **Reuse the report tone, not a role.** If asked to "send the report to the parent," the correct current-state answer is: the *branch admin* generates and forwards it; there is no in-app parent delivery.

## Future Improvements (all greenfield — Not implemented)

A hypothetical parent/guardian role would need, at minimum:

1. **Guard + provider + model + table** — `v2_parent` guard, `v2_parents` table, `App\Models\V2\Parent` (name-collision risk with PHP `parent` — use `Guardian`), following the `SuperAdmin`/`Student` auth pattern (bcrypt, `must_change_password`, `session_version`).
2. **Child linkage** — a `v2_parent_student` (many-to-many) pivot, since a parent may have multiple children and a child two guardians.
3. **A strict read-only tenancy scope** — a parent must see **only their own child(ren)'s** released results/reports and **nothing** cohort-wide. This is the highest-risk scope in the system; model it on the release-gated, ownership-checked student surfaces (see [05 — Student](./05-student.md) §AI risks and [module 12](../modules/12-mistake-bank.md) §Security).
4. **Delivery** — an in-app inbox and/or email of the existing branch-admin report.
5. **AI note** — a parent-facing AI summary must inherit the release gate and per-child ownership, and must never expose peers' data.

Everything above is **Not implemented / future**.
