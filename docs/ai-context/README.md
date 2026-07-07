# AI-Context Documentation Pack

> Code-grounded reference for TopicalEd / cambpast-app (V2). Written to give future
> AI-coding agents and product planning full context **before** AI features are built.
> Every claim cites real code; where the code was ambiguous, docs say "unclear from
> codebase" rather than guess. This pack is documentation only — no app code changed.

## How to use this pack

**The pack is 33 files:** 3 top-level (`README`, `00-system-overview`,
`NEXT_SESSION_AI_TUTOR_PROMPT`) + 5 in `ai/` + 19 in `modules/` + 6 in `roles/`.

1. Read `00-system-overview.md` for the map and the stack.
2. Dive into `modules/` for subsystem detail and `roles/` for per-persona journeys.
3. For AI work, read `ai/` (existing usage → opportunities → contracts → safety →
   roadmap), then start from `NEXT_SESSION_AI_TUTOR_PROMPT.md`.

```
00-system-overview.md            entry map · stack · V2 scope · no-parent-role fact
NEXT_SESSION_AI_TUTOR_PROMPT.md  kickoff prompt for the fresh Student-AI-Tutor session
roles/    01 super-admin · 02 school-admin · 03 branch-admin · 04 teacher · 05 student · 06 parent (not implemented)
modules/  01 tenancy · 02 org · 03 auth · 04 question-bank · 05 versioning/QR · 06 flagging/void ·
          07 propagation · 08 exam-builder · 09 attempts/results · 10 analytics · 11 reports ·
          12 mistake-bank · 13 learning-assets · 14 widgets · 15 notes · 16 notifications ·
          17 secure-images · 18 import-pipeline · 19 audit-logging
ai/       01 existing-usage · 02 opportunities · 03 context-contracts · 04 safety-rules · 05 roadmap
```

*Note: the repo also has an older `docs/modules/` set and other `docs/*.md`. This pack
(`docs/ai-context/`) is the AI-oriented, freshly-verified layer; where the older docs
overlap they are referenced, not duplicated.*

---

## Executive summary

TopicalEd is a **multi-tenant Cambridge O/A-Level exam + learning platform**: schools →
branches → grades → classes → teachers/students, with a question bank, teacher-built
exams, auto-marked attempts, per-role analytics, a Mistake Bank, AI-reviewed learning
assets (worked solutions, flashcards, interactive widgets), and a Notion-style Notes
module. **Five roles** (super-admin, school-admin, branch-admin, teacher, student) —
**no parent role exists**. Tenancy is enforced by Eloquent global scopes, not middleware.

The single most important finding for AI planning: **AI is already partly live.**
`app/Services/V2/ReportService.php` generates the branch-admin student-report narrative via
**OpenAI (`gpt-4o-mini`)** with a deterministic fallback, and already encodes the exact
safety patterns this pack recommends — minimal PII (first name only), stats-grounding, and
a hard "recommend only real syllabus topics, never invent" rule. That service is the house
precedent; everything else AI is greenfield.

**Recommended immediate provider decision:** put new AI behind an `App\Services\AI\*` seam
(mirroring the `config/services.openai` block) and standardize new features on
Claude/Anthropic, leaving the existing OpenAI report call in place as an optional later
migration.

---

## Module-by-module status

| Module | Status | Notes (code-grounded) |
|---|---|---|
| Roles / permissions / tenancy | Implemented | Global scopes per guard; **sharp edge:** some models (Grade, SchoolSubject, ClassTeacher) scope only for school-admin and add **no** filter for teacher/branch/student — verify before trusting for isolation. |
| School / branch / class structure | Implemented | school → branch → grade+subject → class; enrollment + class-teacher M2M. |
| Auth / onboarding / credentials | Implemented | Temp passwords + forced change for admins/teachers; **students never forced**; `session_version` middleware mounted only on the school-admin group. |
| Question bank | Implemented | Correctness is `v2_questions.correct_answer` (options have **no** `is_correct`). Deterministic (non-LLM) tagging via `QuestionTopicClassifier`. |
| Versioning / Quality Review | Implemented (P1–2) | Immutable `v2_question_versions`; exams freeze `question_version_id` — the canonical "what the student saw." |
| Flagging / void engine | Implemented | Student soft-flag → teacher dismiss/escalate → void; `void_source ∈ {teacher, quality_review}`. |
| Quality propagation | Implemented (P3) | `PropagateMaterialCorrection` job voids historical pivots + adjusts attempts. |
| Exam builder | Implemented | V2 = `ExamController` + Blade + Alpine + `ExamService`. **Livewire `TestGenerator`/`QuestionPicker` are legacy V1 — do not extend for V2.** |
| Attempts / checking / results | Implemented | Auto-marked at submit; `results_released_at` gate. `adjusted_score/total` path is **unclear from codebase** — verify before use. |
| Analytics / statistics | Implemented | Live-computed (no analytics tables). `ExamService::studentStats()` is the raw material for AI reports. |
| Reports (current) | **Partially implemented** | `ReportService` (OpenAI narrative + fallback), BranchAdmin-only, stored in `v2_reports.payload`. No parent report. |
| Mistake Bank | Implemented | One durable row per student+question; release-gated; `practiced` status + `confidence` exist but have **no writer yet**. |
| Learning Hub / assets | Implemented | 8 asset types; visible only when `approved`/`edited`; **generation happens outside the app**. |
| Interactive widgets | Implemented | 31 React island widgets; config from `{widget, config}`; not persisted. |
| Notes | Implemented | `v2_notes_*`; import bridge; `question_figure` render-time signed-URL references. The AI-tutor save target. |
| Notifications | Implemented | Attention (weak-topic) + update types; teacher/student inboxes only (**no admin inbox**). |
| Secure images | Implemented | Viewer-bound, expiring HMAC URLs — AI must pass image **references**, never baked URLs. |
| Import & data pipeline | Implemented | 18 `v2:*` commands + export/restore; deterministic tagging; legacy `cambpast:import` is V1. |
| Audit logging | **Partially implemented** | Writes live (`AuditLogger` → `v2_audit_logs`); **read/viewer UI is a stub**; `v2_login_attempts` is record-only. |
| Existing AI usage | **Partially implemented** | The only live LLM call — `ReportService` → OpenAI `gpt-4o-mini`. No AI-usage/cost logging table. |

---

## AI-ready now

- **AI reports** — the pipeline already exists (`ReportService`), is grounded, and degrades
  safely. Extending to teacher-class / subject / school summaries mostly reuses
  `ExamService::studentStats()` + `PlatformStatsService`.
- **Student AI Tutor / Mistake explainer** — the required context payload maps almost 1:1
  onto a `StudentMistake` row plus the eager loads already in
  `LearningHubController::studio()` (question, options, correct+selected answer, worked
  solution, option explanations, topic/subtopic, mistake count, mastery). Trusted assets
  are gated by `QuestionLearningAsset::VISIBLE_STATUSES = [approved, edited]`.
- **Notes as AI output sink** — the Notes import/block system can store AI answers as a
  new block, cleanly separated from imported source-of-truth content.

## Needs more data / modeling before AI

- **`ai_interactions` logging table** — none exists; required for cost/debug (model this on
  `v2_audit_logs` / `AuditLogger`).
- **Provider seam** — introduce `App\Services\AI\*` before scaling beyond the one OpenAI call.
- **Retrieval / embeddings** — nothing exists; needed for syllabus/worked-solution/notes/
  mistake-pattern retrieval (roadmap Phase 5).
- **Cross-cohort misconception analytics** — a distractor/wrong-answer taxonomy is not
  modeled; a strong future moat but greenfield.
- **Per-question answer-key confidence** — correctness is a single `correct_answer` char;
  no confidence signal for AI to weigh.

---

## Recommended next task for Fable 5

**Build Student AI Tutor V1** (roadmap Phase 1) in a fresh session using this pack:
1. Add an `App\Services\AI\*` provider seam (Claude/Anthropic) + `config` block mirroring
   `services.openai`.
2. Add an `ai_interactions` table (model, tokens/cost, user role, source-context type,
   grounded on `AuditLogger`'s pattern) and log every call.
3. "Ask AI" from a worked solution and from a mistake, using **current question/mistake
   context only** (per the Student-Tutor contract in `ai/03`), with the safety rules in
   `ai/04` (grounded-only, say-when-insufficient, never invent marks/topics, never cross
   tenancy, save output to Notes not source records).

Full kickoff prompt: **`NEXT_SESSION_AI_TUTOR_PROMPT.md`**.
