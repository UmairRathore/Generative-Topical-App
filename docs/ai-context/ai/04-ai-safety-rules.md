# 04 — AI Safety Rules

> Forward-looking AI design doc. Grounded in current code; features described here are NOT yet built unless labelled **Implemented**.

**Cross-links:** [01 — Existing AI Usage](./01-existing-ai-usage.md) ·
[02 — AI Integration Opportunities](./02-ai-integration-opportunities.md) ·
[03 — AI Context Contracts](./03-ai-context-contracts.md) ·
[05 — AI Roadmap](./05-ai-roadmap.md) ·
Modules: [../modules/01-roles-permissions-tenancy.md](../modules/01-roles-permissions-tenancy.md) (tenancy/scopes) ·
[../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md) ·
[../modules/19-audit-logging.md](../modules/19-audit-logging.md) (see also `docs/modules/11-foundation-shared.md`)

---

These are **hard rules**, not guidelines. Each is tied to a code anchor that already enforces the
underlying invariant, so an AI feature that respects the anchor cannot violate the rule. Where no
anchor exists yet, the rule is labelled **Recommended for AI** and a build target is given.

---

## Rule 1 — Never invent marks, scores, or results

Auto-marking is the **single source of truth**. Marks are computed in
`ExamService::submit()` (`app/Services/V2/ExamService.php:178–228`) against the frozen version's
answer key, and scores are re-derived by `recomputeExamScores()` (line 278) — the AI has no role in
scoring. The `correct_answer` on a question (frozen per version) is authoritative; an AI explanation
or report must **read** it, never override it or state a different result.

- **Anchor:** `ExamService::submit()`, `recomputeExamScores()`, `v2_exam_answers.is_correct`.
- **Applies to:** tutor, mistake-explanation, all reports.
- **Test:** an AI answer that contradicts `Question::correct_answer` for the question is a bug.

## Rule 2 — Never expose another student's data

The system enforces per-student and per-school isolation with **model global scopes**. `StudentMistake`
adds a `school` global scope keyed to the authenticated portal guard's `school_id`
(`StudentMistake::booted()`, `app/Models/V2/StudentMistake.php:56–67`), mirroring `Exam`/`ExamAttempt`.
Ownership is additionally checked per request: `LearningHubController::authorizeMistake()` 403s on a
foreign mistake (`LearningHubController.php:210–213`).

- **Anchor:** global scopes ([../modules/01-roles-permissions-tenancy.md](../modules/01-roles-permissions-tenancy.md) / roles-access-control);
  `authorizeMistake()`.
- **Rule:** an AI feature must assemble context **only** from rows the current user could already
  read through these scopes. Never join another student's answers, notes, or mistakes into a prompt.
  `related_notes` in the tutor payload ([03/A](./03-ai-context-contracts.md#a-student-ai-tutor)) is
  the student's OWN notes only.

## Rule 3 — Respect school / branch / class tenancy

Every rollup service is scoped by tenant: `studentStats` accepts a subject scope; `schoolOverview`,
`schoolClassRows`, `schoolTeacherRows`, `schoolTopicStats` all take `$schoolId` (+ optional
`$branchId`); teacher aggregates key on `created_by`/class membership
(`app/Services/V2/ExamService.php`). The **only** deliberately cross-school surface is
`PlatformStatsService` for the super admin.

- **Rule:** an AI report must be built from the tenant-scoped aggregate matching the caller's role
  (student→self, teacher→own classes, school admin→one school, branch admin→one branch). Never feed
  a school admin another school's data, or a teacher another teacher's class.
- **Anchor:** the `$schoolId`/`$branchId`/`created_by` parameters throughout `ExamService`;
  see the per-role scope table in [03/E](./03-ai-context-contracts.md#e-ai-report-generator).

## Rule 4 — Tutor prefers TRUSTED assets over free generation

Students may only ever see super-admin-blessed learning content. `MistakeBankService::visibleAsset()`
returns only assets whose status is in `QuestionLearningAsset::VISIBLE_STATUSES` = `[approved, edited]`
(`app/Models/V2/QuestionLearningAsset.php:44–45`, service `MistakeBankService.php:310–321`), preferring
`approved` then `edited`. Draft / generated / reviewed / rejected / hidden content is invisible.

- **Rule:** the tutor grounds explanations in these visible assets first (worked_solution,
  option_explanation, …). Free generation is a **last resort** only when no visible asset exists,
  and even then it is bounded by stem + options + `correct_answer` and must flag its lack of source
  material (rule 5). AI must never surface a non-visible asset.
- **Anchor:** `visibleAsset()`, `VISIBLE_STATUSES`,
  [../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md).

## Rule 5 — Say when context is insufficient

If the grounding material is thin or absent, the model must say what it can and **state the
limitation** rather than confabulate. This is the interactive analogue of the live report's empty
-window short-circuit (`ReportService::narrative()` returns a plain "no tests … to report yet" when
`tests === 0`, `app/Services/V2/ReportService.php:35–37`).

- **Rule:** encode this in every system prompt (see the `answer_style.rules` arrays in
  [03](./03-ai-context-contracts.md)). No visible worked solution → say the explanation is
  unassisted. No stats in window → say so; do not fabricate a trend.

## Rule 6 — Report generators must ground conclusions in real stats

The precedent is explicit: recommendations may be drawn **only** from the subject's real
`syllabus_topics` list — *"never invent a topic that is not in that list"*
(`ReportService::openai()` prompt, `app/Services/V2/ReportService.php:80–82`). Numbers are the
evidence; the syllabus is the allowed vocabulary.

- **Rule:** any AI report must cite/derive its claims from the `ExamService`/`PlatformStatsService`
  aggregates it was given, and may only name topics/subtopics/classes/teachers that appear in that
  data. No invented trends, marks, or curriculum items.
- **Anchor:** the grounding rule in `ReportService`; the `all_topics` field carried through
  `studentStats()` (`ExamService.php:485–521`).

## Rule 7 — Exam generator obeys teacher topics and counts

The AI's job is **selection over `v2_questions`**, not authoring. The deterministic engine already
constrains draws to `active()->has('options')` questions scoped to the class subject + selected
topics + year range (`ExamService::drawRandomIds()` / `createFromQuestions()`,
`app/Services/V2/ExamService.php:64–113`).

- **Rule:** obey teacher-selected topics and per-topic counts exactly; every output id must exist in
  `v2_questions` and pass the same filter; do not invent or random-fill unsupported questions unless
  explicitly allowed (`allow_unsupported_random_fill`). Freezing stays with `ExamService::freeze()`.
- **Anchor:** `drawRandomIds()`, `createFromQuestions()`, `freeze()`;
  see [03/D](./03-ai-context-contracts.md#d-ai-exam-generator).

## Rule 8 — Never modify official records without confirmation

Marks, exam questions, voids, releases, mistake mastery transitions, and report source stats are all
human/deterministic decisions. Mistake status transitions are **student-initiated** via an explicit
action (`LearningHubController::updateStatus()` validates `action ∈ {mastered, reset, archive}`,
`LearningHubController.php:168–187`); voids are teacher-initiated (`ExamService::voidExamQuestion()`).

- **Rule:** an AI feature must not silently write to `v2_exam_answers`, `v2_exam_questions`,
  `v2_student_mistakes.status`, `v2_reports` stats, or release fields. If an AI *suggests* an action
  (e.g. "mark this mastered"), a human confirms and the existing service method performs it.
- **Anchor:** `updateStatus()`, `voidExamQuestion()`, `Exam::resultsReleased()`.

## Rule 9 — AI outputs are stored SEPARATELY from source-of-truth

Never overwrite source data. The Notes import bridge already writes platform content as **new
blocks** stamped with a `source` (`app/Http/Controllers/V2/Student/NotesImportController.php`), and
`NotesDocumentService::apply()` snapshots a version on every save
(`app/Services/V2/NotesDocumentService.php:33–51, 77–93`).

- **Rule:** AI-generated content goes into **new tables or new blocks**, never overwriting:
  - Notes AI → new **AI-authored blocks** (recommend `origin: "ai"`), kept separate from imported
    source-of-truth blocks (see [03/C](./03-ai-context-contracts.md#c-notes-ai)).
  - Tutor explanations → saved to Notes as new blocks, and logged (rule 10); never written to
    `QuestionLearningAsset` (that store is super-admin-gated).
  - Reports → new `v2_reports` rows; the AI narrative lives in `payload.narrative`, the *stats* in
    `payload.stats` are the deterministic source and are never AI-edited.
- **Anchor:** `NotesImportController`, `NotesDocumentService`, `v2_reports.payload` structure.

## Rule 10 — Log ALL AI usage (cost / debug / audit)

**Recommended for AI — not yet built.** Today the only trace is `v2_reports.source` and an ad-hoc
`Log::warning` on failure ([01](./01-existing-ai-usage.md)). Build a dedicated `ai_interactions`
table following the `v2_audit_logs` pattern (migration `2026_06_13_000006_create_v2_audit_logs_table.php`;
service `app/Services/V2/AuditLogger.php`).

Proposed `ai_interactions` schema (mirrors `v2_audit_logs` shape):

```
id
actor_type            // class_basename of the acting model (Student/Teacher/…) — as AuditLogger does
actor_id
role                  // caller guard: v2_student / v2_teacher / …
feature               // 'tutor' | 'mistake_explanation' | 'notes_summarise' | 'report_student' | 'exam_gen' | …
source_context_type   // 'mistake' | 'worked_solution' | 'notes_page' | 'report' | …
source_context_id     // e.g. student_mistake_id / page_id / report_id
provider              // 'anthropic' (recommended default) | 'openai'
model                 // resolved model id
prompt_tokens         // nullable if provider doesn't return it
completion_tokens     // nullable
total_tokens          // nullable
cost_usd              // nullable (compute from tokens; see /claude-api)
latency_ms            // nullable
status                // 'ok' | 'fallback' | 'error'
error                 // nullable message
request_json          // nullable, redacted (NO raw PII beyond first name — rule 11)
response_json         // nullable, truncated
school_id             // tenancy for cost attribution
created_at
index (actor_type, actor_id), index (feature, created_at), index (school_id, created_at)
```

- **Rule:** every AI call writes one `ai_interactions` row (including the fallback path). Redact the
  payload to the same minimal-PII standard as the call itself (rule 11). Build this **before or
  alongside** the first new AI feature ([05](./05-ai-roadmap.md), Phase 1).
- **Anchor:** `AuditLogger` / `v2_audit_logs` as the structural precedent.

## Rule 11 — Minimal-PII default (first-name precedent)

The live call sends the student's **first name only** — `strtok($student->name, ' ')`
(`app/Services/V2/ReportService.php:55`) — plus numbers. No surname, email, roll number, id, or
school/branch name leaves the app.

- **Rule:** every AI payload defaults to first-name-only for a student and role-appropriate minimal
  identity otherwise. Aggregate over individuals where the feature allows. Never send emails, full
  names, or ids to the model unless a specific feature requires it and it is documented.
- **Anchor:** `ReportService::openai()`/`fallback()` first-name extraction.

## Rule 12 — Release-gating is respected

A mistake (and its question review/studio/asset) is only reachable once the source exam's results
are released: `Exam::resultsReleased()` = `results_released_at !== null`
(`app/Models/V2/Exam.php:137`), enforced in `LearningHubController` (`review`/`studio`/`asset`
`abort_unless(... resultsReleased())`, lines 57, 93, 194) and in `MistakeBankService::releasedQuery()`
(`app/Services/V2/MistakeBankService.php:157–164`). Notes figure URLs re-check the same gate
(`NotesDocumentService::figureUrls()`, lines 213–217).

- **Rule:** no AI feature may read a mistake, question, or answer whose exam results are unreleased —
  that would bypass the teacher's release decision. The tutor/mistake-explanation contracts in
  [03](./03-ai-context-contracts.md) list this as a precondition.
- **Anchor:** `resultsReleased()`, `releasedQuery()`, the `abort_unless` guards.
