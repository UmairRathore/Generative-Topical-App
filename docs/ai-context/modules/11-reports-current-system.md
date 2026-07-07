# 11 — Reports (current system)

> Code-grounded reference. Verify against source before acting.

**Siblings:** [08 — Exam Builder](./08-exam-builder.md) ·
[09 — Exam Attempts / Checking / Results](./09-exam-attempts-checking-results.md) ·
[10 — Analytics & Statistics](./10-analytics-and-statistics.md) ·
[12 — Mistake Bank](./12-mistake-bank.md)

**AI usage:** the narrative-generation internals (OpenAI call, prompt, fallback) are described only at
a high level here — see **[`../ai/01-existing-ai-usage.md`](../ai/01-existing-ai-usage.md)** for the deep dive.

---

## Purpose

A **student progress report**: per-subject / per-topic / per-subtopic performance over a chosen time
window, plus a short written **narrative** (AI when configured, deterministic template otherwise),
saved as JSON and rendered to a downloadable **PDF** on demand.

Status: **Implemented in code** (generation + PDF). The AI narrative is **partially implemented** —
it runs only when `OPENAI_API_KEY` is set; otherwise a deterministic fallback is used.

## Users / Roles

- **Branch Admin only** generates reports today. Routes are under the branch-admin group
  (`routes/v2.php:255-256`): `POST students/{student}/reports` (`report.generate`) and
  `GET reports/{report}/pdf` (`report.pdf`).
- Branch/School analytics pages surface **existing** reports read-only
  (`BranchAdmin/AnalyticsController::student` loads the latest 12, line 135).
- **No parent report and no parent role exists** in the system — reports are an admin artifact, not a
  parent-facing portal. (Verified: no parent guard/role anywhere.)
- Teachers, students and super/school admins **cannot generate** reports (only the branch admin route
  exists).

## Current Implementation

### Controller — `app/Http/Controllers/V2/BranchAdmin/ReportController.php`
- `generate(Student, Request, ReportService)` — asserts `student->branch_id === branchId()` (403
  otherwise), resolves the window (`window()`), calls `ReportService::build()`, then persists a
  `Report` row (`payload` = the full stats+narrative array; denormalized `overall_avg`, `tests_count`,
  `source`). **A zero-test window is not an error** — an empty report is still saved with an `info`
  flash. Redirects back to the student page with `report_ready` = new report id.
- `pdf(Report)` — asserts `report->branch_id === branchId()`, renders
  `v2.branch_admin.report.pdf` from the **stored JSON** (`payload['stats']`, `payload['narrative']`)
  via `barryvdh/dompdf`, and streams a download. **The PDF is not stored** — always re-rendered from
  JSON (saves space; JSON stays reusable).
- `window(Request)` — maps `duration` to `[since, until, label, key]`. Presets: week / 2weeks /
  3weeks / month(default, 31d) / 2months / 3months / quarter / 6months / 10months / all, plus a
  validated `custom` from–to range.

### Service — `app/Services/V2/ReportService.php`
- `build(Student, since, periodLabel, ?until)` → `['stats' => studentStats(...), 'narrative' => ...]`.
  **Stats come straight from `ExamService::studentStats()`** ([module 10](./10-analytics-and-statistics.md)) —
  the report adds only the narrative on top.
- `narrative()` — if `overall.tests === 0`, returns a "no tests" narrative (`source:'none'`). Else, if
  `config('services.openai.key')` is set, calls `openai()` (model `config('services.openai.model',
  'gpt-4o-mini')`); on any throwable it logs and **falls back** to the deterministic template.
- `openai()` / `fallback()` — narrative construction. **See `../ai/01-existing-ai-usage.md` for the
  prompt, the strict-JSON contract, the "syllabus_topics is the only recommendation pool" guardrail,
  and privacy scope (only the student's first name + numbers are sent).** Not deep-dived here by design.

### Model — `app/Models/V2/Report.php`
Table `v2_reports`. Uses `HasHashid`. `payload` cast to `array`; `range_from`/`range_to` to datetime.
Relations: `student`, `branch`, `school`. **No global scope** — access is controlled by the
controller's `branch_id` check.

### Migration — `2026_06_18_000002_create_v2_reports_table.php`
| Column | Meaning |
|---|---|
| `school_id`, `branch_id`(nullable), `student_id` | ownership |
| `generated_by`(nullable) | the branch-admin id who generated it |
| `period_key`(20), `period_label` | window key + human label |
| `range_from`, `range_to`(ts nullable) | window bounds (`range_from` null for all-time) |
| `overall_avg`(tinyint nullable), `tests_count`(smallint) | denormalized summary (listable without parsing JSON) |
| `source`(20, default `template`) | `openai` / `template` / `none` |
| `payload`(json) | the full `{stats, narrative}` — PDF renders from this |

> **Correction to a common description:** the report does **not** store separate "narrative JSON" and
> "stats JSON" columns, nor a `generated_at`. Both live inside the single `payload` JSON; the timestamp
> is the standard `created_at` (the PDF uses `$report->created_at`).

### View — `resources/views/v2/branch_admin/report/pdf.blade.php`
The A4 PDF template. Receives `student`, `branch`, `school`, `period`, `stats`, `narrative`,
`generatedAt` (= `created_at`).

### Seeder — `database/seeders/V2ReportDemoSeeder.php`
Seeds demo reports (referenced by the branch report flow / demo data).

### Commands / Jobs / Tests
- **Commands/Jobs:** none — report generation is a synchronous controller action.
- **Tests:** **None** for report generation, the window resolver, persistence, or PDF rendering.
  Coverage gap.

## Data Model
One `v2_reports` row per generation (append-only; regenerating creates a new row — the branch student
page shows the latest 12). `payload` = `{ stats: <studentStats shape>, narrative: {source, summary,
subjects} }`. See [module 10](./10-analytics-and-statistics.md) for the exact `stats` shape.

## Core Flows
Branch admin opens a student's analytics page → picks a duration (or custom range) → POST `generate`
→ `build()` (stats + narrative) → `Report::create` (payload + denormalized summary) → redirect with
`report_ready`. Later, GET `pdf` re-renders from the stored JSON and downloads.

## Inputs
- `generate`: route student + `duration` (preset key) or `duration=custom` + `from`/`to` (validated,
  not future, `to ≥ from`).
- `pdf`: route report.

## Outputs
- Persisted `v2_reports` row (stats + narrative in `payload`, summary denormalized).
- On demand: a downloadable A4 PDF, `report-{slug}-{Y-m-d}.pdf`.
- Narrative shape: `{ source: 'openai'|'template'|'none', summary: string, subjects: { <subject> =>
  string } }`.

## Dependencies
- `ExamService::studentStats()` — the entire statistical basis ([module 10](./10-analytics-and-statistics.md)).
- `config('services.openai.*')` (`OPENAI_API_KEY`, `OPENAI_MODEL`) — optional; drives AI vs. fallback.
- `barryvdh/dompdf` — PDF rendering.
- `HasHashid` — obfuscated report ids in URLs.

## Security / Access Rules
- Branch-scoped: both `generate` and `pdf` assert `branch_id === branchId()`; the student must belong
  to the admin's branch.
- The model has no global scope, so the controller checks are the **only** guard — any new report
  route must repeat them.
- Privacy: only the student's **first name + numeric stats** are sent to OpenAI (never full PII) — see
  the AI-usage doc.

## Existing AI-Relevant Context
- **This is the one place AI is wired into the reporting/analytics surface today** (optional OpenAI
  narrative, `gpt-4o-mini`, strict-JSON, deterministic fallback). It is deliberately *thin*: AI writes
  prose over deterministic stats; it never computes or alters a number.
- The `all_topics` / `syllabus_topics` guardrail (recommend prerequisites **only** from real syllabus
  topics) is the key correctness pattern any future AI feature should copy.

## AI Opportunities *(Recommended for AI — not implemented)*
- **Wider audience:** teacher/class/school-level narratives (the analytics shapes already exist,
  module 10) — currently only per-student, branch-admin-generated.
- **Parent-facing report** (would require a new parent role — none exists today).
- **Trend narratives** across successive reports (multiple `v2_reports` rows per student already
  accumulate).
- **Mistake-Bank-aware recommendations** ([module 12](./12-mistake-bank.md)) folded into the narrative.

## AI Risks
- **Hallucinated recommendations:** the prompt already constrains prerequisites to `syllabus_topics`;
  any change must preserve that guard or the report will invent non-existent topics.
- **Fallback silence:** if OpenAI fails, the deterministic template runs — features must not assume AI
  ran (`narrative.source` records which path was used).
- **Stat drift:** the report snapshots stats into `payload` at generation; later voids/regrades
  ([module 09](./09-exam-attempts-checking-results.md)) do **not** update an already-saved report.
- **Cost/latency:** synchronous OpenAI call in a request (30s timeout) — a queued job would be safer at
  scale.

## Future Improvements
- Add tests (window resolver edge cases, empty-window save, PDF render, AI-vs-fallback branch).
- Move generation to a queued job (currently blocks the request on the OpenAI call).
- Broaden beyond branch-admin/per-student once role/audience requirements are defined.
