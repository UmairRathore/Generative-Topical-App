# 02 — AI Integration Opportunities

> Forward-looking AI design doc. Grounded in current code; features described here are NOT yet built unless labelled **Implemented**.

**Cross-links:** [01 — Existing AI Usage](./01-existing-ai-usage.md) ·
[03 — AI Context Contracts](./03-ai-context-contracts.md) ·
[04 — AI Safety Rules](./04-ai-safety-rules.md) ·
[05 — AI Roadmap](./05-ai-roadmap.md) ·
Modules: [../modules/12-mistake-bank.md](../modules/12-mistake-bank.md) ·
[../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md) ·
[../modules/15-notes-module.md](../modules/15-notes-module.md) ·
`docs/modules/07-exams-tests.md` · `docs/modules/08-stats-analytics.md` · `docs/modules/09-reports-pdf.md`

---

## How to read this file

Each product goal is mapped to **the data that already exists to feed it** (cited to real
services/modules), a **status label**, and a **readiness verdict**:

- **AI-ready now** — the grounding data exists and is queryable today; you can build the contract in
  [03](./03-ai-context-contracts.md) and ship.
- **Needs more data / modeling** — the base data is thin, unlabelled, or the aggregation layer
  doesn't exist yet; build the data foundation first.

Status labels: **Implemented in code / Partially implemented / Not implemented / future /
Recommended for AI**. Only the branch-admin report narrative is live today (see
[01](./01-existing-ai-usage.md)).

---

## The 9 product goals

### 1. Student AI Tutor

Ask-AI grounded in the current question/mistake — *"why is C right and my B wrong?"*, *"teach me
this topic."*

- **Data that exists today:**
  - One `StudentMistake` row per (student, question) with the student's own wrong `selected_option`,
    the `correct_option`, denormalised subject/topic/subtopic/difficulty, `mistake_count`, mastery
    `status`, and full event history — `app/Models/V2/StudentMistake.php`,
    `app/Services/V2/MistakeBankService.php`, [../modules/12-mistake-bank.md](../modules/12-mistake-bank.md).
  - Trusted, super-admin-approved per-question learning content (worked solution, option
    explanations, flashcards, memcards, mermaid, interactive widget) gated behind
    `MistakeBankService::visibleAsset()` / `QuestionLearningAsset::VISIBLE_STATUSES` —
    `app/Models/V2/QuestionLearningAsset.php`, [../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md).
  - Question stem, correct answer, options, and diagram figures (signed via `SignedImage`) already
    assembled in `LearningHubController::studio()` (`app/Http/Controllers/V2/Student/LearningHubController.php:90–166`).
  - Behaviour signals via `ExamService::studentStats()` / `studentScoreTrend()` /
    `studentTopicStats()` (`app/Services/V2/ExamService.php`).
- **Status:** **Not implemented / future.** No AI runs in the Mistake Bank today
  (`MistakeBankService` docblock: *"No AI here."*).
- **Readiness:** **AI-ready now** for the *per-current-question* tutor. The exact payload maps 1:1
  onto a mistake row + `studio()`'s existing eager loads — see
  [03 — Student AI Tutor contract](./03-ai-context-contracts.md#a-student-ai-tutor). This is the
  recommended **Phase 1** ([05](./05-ai-roadmap.md)).
- **Not ready:** open-ended "teach me the whole topic" via retrieval — needs embeddings
  (Phase 5).

### 2. AI Reports — Student

A student-facing version of the progress narrative ("here's how you're doing").

- **Data that exists today:** `ExamService::studentStats()` (overall + per-subject + per-topic +
  per-subtopic accuracy + full syllabus topic list) — the exact shape the live report already
  consumes; plus `studentScoreTrend()`, `studentMonthlyActivity()`, `studentImprovement()`,
  `studentTopicStats()` (all in `app/Services/V2/ExamService.php`).
- **Status:** **Partially implemented.** The generator exists (`ReportService`) but is wired only to
  the **Branch Admin** role (`app/Http/Controllers/V2/BranchAdmin/ReportController.php`). A
  student-scoped invocation does not exist.
- **Readiness:** **AI-ready now.** Reuse `ReportService::build()`; add a student-scoped controller +
  the privacy boundary (student sees only their own data). Contract in
  [03 — AI Report Generator / student](./03-ai-context-contracts.md#e-ai-report-generator).

### 3. AI Reports — Teacher (class)

A class summary for a teacher: how the class is doing, weak topics, who needs attention.

- **Data that exists today:** `ExamService::classTopicStats()`, `classStudentMatrix()`
  (per-student × per-topic matrix), `teacherTopicStats()`, `teacherStudentPerformance()`
  (worst-first), `teacherScoreDistribution()`, `teacherExamTrend()`, `teacherOverview()`
  (`app/Services/V2/ExamService.php`).
- **Status:** **Not implemented.** No AI narrative for a class exists.
- **Readiness:** **AI-ready now.** All the aggregates exist and are already used by the teacher
  dashboards ([../modules/../modules/../modules — `docs/modules/08-stats-analytics.md`]).
  Contract: [03 — teacher-class report](./03-ai-context-contracts.md#e-ai-report-generator).
  Privacy: scope strictly to the teacher's own classes/students.

### 4. AI Reports — Branch admin

Live today (the precedent). See [01](./01-existing-ai-usage.md).

- **Status:** **Partially implemented** (the one live feature).
- **Readiness:** shipped for the per-student narrative. A branch-wide *rollup* narrative
  (aggregate over all students in the branch, not one student) is **Not implemented** but
  **AI-ready now** via `ExamService::schoolOverview($schoolId, $branchId)`,
  `schoolClassRows(…, $branchId)`, `schoolTeacherRows(…, $branchId)`, `schoolTopicStats(…, $branchId)`.

### 5. AI Reports — School admin

School-wide summary narrative.

- **Data that exists today:** `ExamService::schoolOverview()`, `schoolBranchRows()`,
  `schoolClassRows()`, `schoolTeacherRows()`, `schoolTopicStats()`, plus the grade/subject/topic
  drill-down aggregates further down `ExamService` (`gradeWideRows()` etc.).
- **Status:** **Not implemented.**
- **Readiness:** **AI-ready now** for a summary; scope to `school_id`. Contract:
  [03 — school-admin report](./03-ai-context-contracts.md#e-ai-report-generator).

### 6. AI Exam Generation

Teacher describes intent (topics, counts, difficulty mix, duration) → AI assembles a paper.

- **Data that exists today:** the whole exam-build engine already selects and freezes questions:
  `ExamService::generate()` (RANDOM), `createFromQuestions()` (CUSTOM), `drawRandomIds()`
  (answerable-preferring random draw scoped to subject + topics + year range), `freeze()`
  (`app/Services/V2/ExamService.php:30–159`); the corpus is `v2_questions`
  (`docs/modules/05-question-bank.md`); topics/subtopics in `v2_topics` / `v2_subtopics`.
- **Status:** **Not implemented** as an *AI* feature. The deterministic generator is
  **Implemented** ([../modules/../modules — `docs/modules/07-exams-tests.md`]).
- **Readiness:** **AI-ready now**, but the AI's job is **selection/orchestration, not question
  authoring.** It must draw from `v2_questions` and obey teacher-selected topics/counts — never
  invent questions. Contract + hard rule:
  [03 — AI Exam Generator](./03-ai-context-contracts.md#d-ai-exam-generator) and
  [04 rule 7](./04-ai-safety-rules.md). This is **Phase 3** ([05](./05-ai-roadmap.md)).

### 7. Trend analysis — individual student / class / subject·topic·subtopic

Narrated trends over time and across the curriculum hierarchy.

- **Data that exists today:**
  - *Individual:* `studentScoreTrend()`, `studentMonthlyActivity()`, `studentImprovement()`,
    `studentTopicStats()`.
  - *Class:* `classTopicStats()`, `classStudentMatrix()`, `teacherExamTrend()`.
  - *Subject / topic / subtopic:* per-topic and per-subtopic accuracy is already produced by
    `studentStats()` (nested subtopics) and the school/grade/subject/topic drill-downs in
    `ExamService`; platform-wide "hardest questions" in
    `PlatformStatsService::hardestQuestions()` (`app/Services/V2/PlatformStatsService.php`).
- **Status:** the **numbers** are Implemented; **AI narration** of them is **Not implemented**.
- **Readiness:** **AI-ready now** for narration (feed the existing aggregates; ground the narrative
  in them, precedent = `ReportService`). *Subtopic*-level trend is available but sparser (only
  subtopics that were actually examined appear).

### 8. Branch-wide / school-wide analytics narratives

Covered by goals 4/5 above at the report layer. The raw rollups
(`schoolOverview`, `schoolBranchRows`, `schoolClassRows`, `schoolTeacherRows`, `PlatformStatsService`
for the super-admin cross-school view) are **Implemented**; the AI narration is **Not implemented /
AI-ready now**.

### 9. AI in Notes / Mistake Bank / Worked Solutions

- **Notes AI** (summarise page, simplify selection, generate flashcards/memcards, create quiz):
  - **Data that exists today:** the block document (`v2_notes_pages.document_json`, BlockNote
    blocks), flattened searchable text (`NotesDocumentService::flattenText()`), block-type index,
    the "add to notes" import bridge that already inserts platform content as new blocks
    (`app/Http/Controllers/V2/Student/NotesImportController.php`), and the block factory
    (`NotesBlockMapper`). See [../modules/15-notes-module.md](../modules/15-notes-module.md).
  - **Status:** **Not implemented.** No AI touches Notes today (grep confirms no provider call in
    any `Notes*` controller/service).
  - **Readiness:** **AI-ready now** for summarise/simplify/flashcards/memcards/quiz *over selected
    text*. The save-target pattern already exists: AI output becomes **new AI-authored blocks**,
    inserted the same way imports are, kept **separate** from imported source-of-truth blocks. This
    is **Phase 4** ([05](./05-ai-roadmap.md)). Contract:
    [03 — Notes AI](./03-ai-context-contracts.md#c-notes-ai).
- **Mistake-Bank AI** = the tutor (goal 1) grounded in a mistake. **AI-ready now** (Phase 1).
- **Worked-solution AI** (authoring the assets themselves): today assets are generated **offline**
  and only displayed in-app ([../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md)).
  In-app AI *authoring* of assets is **Not implemented / future** and is intentionally gated on the
  super-admin review lifecycle (`QuestionLearningAsset` statuses) — AI drafts, humans approve.

---

## The moat: cross-cohort misconception analytics (future)

`v2_exam_answers.selected_option` records the **specific distractor** each student chose (confirmed
populated; used by `MistakeBankService` capture and `ExamService::submit`). At **cohort scale** this
is a misconception signal — *"60% of students who missed this picked C."* The missing piece is a
one-time, reusable **distractor taxonomy**: label each wrong option with the misconception it
represents (an offline batch enrichment, like the existing topic-tagging pass). Once questions carry
that, per-class/school/subtopic misconception maps are cheap aggregation, and the tutor can teach
*against the student's specific misconception* rather than just the subtopic.

- **Status:** **future** (design intent, not built). Documented in
  [../modules/12-mistake-bank.md](../modules/12-mistake-bank.md) ("Misconception clustering") and in
  the project roadmap notes.
- **Caveat:** many science MCQs use **image options** (`option.text = ""`), so text-based distractor
  inference is partial — the taxonomy pass must handle image-only options.
- **Readiness:** **Needs more data / modeling** (the distractor labels). This is **Phase 5+** and is
  the connective tissue between cohort analytics (goals 3–8) and a truly targeted tutor (goal 1).

---

## Readiness summary

| Goal | Status | Readiness | Phase |
|---|---|---|---|
| 1. Student AI Tutor (current-question) | Not implemented | **AI-ready now** | 1 |
| 2. AI Report — student | Partially implemented (engine exists) | **AI-ready now** | 2 |
| 3. AI Report — teacher/class | Not implemented | **AI-ready now** | 2 |
| 4. AI Report — branch (per-student) | **Partially implemented (live)** | shipped | — |
| 4b. AI Report — branch-wide rollup | Not implemented | **AI-ready now** | 2 |
| 5. AI Report — school | Not implemented | **AI-ready now** | 2 |
| 6. AI Exam Generator | Not implemented (engine exists) | **AI-ready now** (selection only) | 3 |
| 7. Trend narration (indiv/class/subject/topic/subtopic) | Numbers Implemented; narration Not implemented | **AI-ready now** | 2 |
| 8. Branch/school-wide narration | Rollups Implemented; narration Not implemented | **AI-ready now** | 2 |
| 9. Notes AI | Not implemented | **AI-ready now** | 4 |
| Cross-cohort misconception analytics | future | **Needs more data** (distractor taxonomy) | 5+ |
