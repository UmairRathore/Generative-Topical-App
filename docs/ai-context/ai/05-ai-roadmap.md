# 05 — AI Roadmap

> Forward-looking AI design doc. Grounded in current code; features described here are NOT yet built unless labelled **Implemented**.

**Cross-links:** [01 — Existing AI Usage](./01-existing-ai-usage.md) ·
[02 — AI Integration Opportunities](./02-ai-integration-opportunities.md) ·
[03 — AI Context Contracts](./03-ai-context-contracts.md) ·
[04 — AI Safety Rules](./04-ai-safety-rules.md) ·
Modules: [../modules/12-mistake-bank.md](../modules/12-mistake-bank.md) ·
[../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md) ·
[../modules/15-notes-module.md](../modules/15-notes-module.md)

---

## Sequencing principle

Ship in order of **grounding readiness** and **blast radius**: start where the grounded data already
exists and the scope is one question (tutor), then narrated aggregates (reports), then orchestration
(exam gen), then Notes generation, then retrieval. Each phase reuses the code from the phase(s)
before and the patterns proven by the live `ReportService` ([01](./01-existing-ai-usage.md)).

**Provider:** standardise new AI on **Claude / Anthropic** behind a small provider-agnostic client
(`App\Services\AI\*`), config mirroring the existing `services.openai` block
(`config/services.php`). Consult the `/claude-api` reference for current model ids, the messages
API, structured JSON output, and token/cost fields before building. Keep the existing OpenAI
`ReportService` call as-is; migrate it only opportunistically once the shared client exists.

**Cross-cutting prerequisite for Phase 1 (and every later phase):** build the `ai_interactions`
logging table first (rule 10, [04](./04-ai-safety-rules.md)). Nothing ships without usage logging.

---

## Phase 1 — Student AI Tutor V1  ← recommended NEXT task

**Goal:** "Ask AI" from a worked solution and from a mistake, grounded in the **current
question/mistake only**, with the answer savable to Notes and every call logged.

**Scope (do build):**
- Ask-AI entry points on the mistake **studio** and **review** pages.
- Assemble the [Student AI Tutor context](./03-ai-context-contracts.md#a-student-ai-tutor):
  stem, options, correct answer, selected answer, diagram **reference**, topic/subtopic, visible
  grounding assets (worked_solution / option_explanation / flashcards / memcards / mermaid / widget),
  mistake_count + mastery status.
- "Mistake explanation" one-shot ([03/B](./03-ai-context-contracts.md#b-mistake-explanation-ai-why-was-my-answer-wrong)).
- Save the AI answer to Notes as a **new AI-authored block** (rule 9).
- Log every call to `ai_interactions` (rule 10): feature, source_context_type, model, tokens/cost if
  available, role.

**Do NOT build (out of scope):** full syllabus embeddings, school-wide AI reports, AI exam
generator, Notes AI generation, voice tutor, autonomous lesson generation (per
`docs/ai-context/NEXT_SESSION_AI_TUTOR_PROMPT.md`).

**Prerequisites:**
- Provider-agnostic AI client (Claude default) + `services.anthropic.*` config.
- `ai_interactions` table (rule 10).

**Reused code:**
- `LearningHubController::studio()` / `review()` — already load the question (+images), resolve each
  `visibleAsset()`, mint signed figure URLs, and know subject/topic/selected_option/correct answer
  (`app/Http/Controllers/V2/Student/LearningHubController.php:52–166`).
- `MistakeBankService::visibleAsset()` (trusted-asset gate, rule 4).
- `StudentMistake` row (all curriculum context denormalised; ownership + release + school gates).
- Notes save via the `NotesImportController` / `NotesBlockMapper` / `NotesDocumentService::apply()`
  bridge pattern.
- `ReportService` as the shape template (system persona + JSON context + grounding + fallback).

**Safety anchors:** rules 1, 2, 4, 5, 9, 10, 11, 12 ([04](./04-ai-safety-rules.md)).

**Risks:**
- *Interactive latency/rate-limits* — unlike the report button, the tutor is synchronous and
  frequent; add timeouts, a spinner, streaming if desired, and graceful "try again" (the blanket
  catch-and-fallback of `ReportService` is not enough UX for a chat).
- *Hallucinated physics when no visible asset exists* — enforce rule 4/5; bound to stem + options +
  correct_answer and flag unassisted answers.
- *Cost creep* — the logging table (rule 10) exists precisely to catch this early.
- *Leaking unreleased content* — reuse the exact release/ownership guards; do not re-implement them.

---

## Phase 2 — AI Reports V1

**Goal:** extend the live narrative beyond the branch-admin-per-student case to **student** and
**teacher-class** summaries, then **branch-wide** and **school-wide** rollups, each with weak-topic
recommendations.

**Scope:** student self-report; teacher class summary; branch-wide + school-wide rollup narratives;
optional trend narration ([02 goals 2–8](./02-ai-integration-opportunities.md)).

**Prerequisites:** Phase 1's AI client + logging. Per-role privacy scoping already exists in the
aggregates.

**Reused code:**
- `ReportService::build()` / `narrative()` / `fallback()` — extend, don't rewrite; keep the grounding
  rule and the deterministic fallback.
- Stats: `ExamService::studentStats()` (individual), `classTopicStats()` / `classStudentMatrix()` /
  `teacherStudentPerformance()` / `teacherScoreDistribution()` / `teacherExamTrend()` (teacher),
  `schoolOverview()` / `schoolClassRows()` / `schoolTeacherRows()` / `schoolTopicStats()` /
  `schoolBranchRows()` (branch/school), `PlatformStatsService` (super-admin cross-school).
- Storage precedent: `v2_reports` (JSON payload + `source`); add scope columns/rows as needed.

**Safety anchors:** rules 3 (tenancy), 6 (ground in stats), 8 (don't edit source stats),
9 (new rows), 11 (aggregate/minimal PII).

**Risks:**
- *Privacy scope leaks across tenants* — the biggest risk; always build from the tenant-scoped
  aggregate matching the caller (rule 3). Add tests that a school admin cannot get another school's
  numbers into a prompt.
- *Averaged narratives hiding outliers* — feed distributions (`teacherScoreDistribution`,
  `classStudentMatrix`), not just means.
- *Fallback drift* — every new scope needs its own deterministic fallback, like the live one.

---

## Phase 3 — AI Exam Generator

**Goal:** teacher states intent (topics, per-topic counts, difficulty mix, duration, year range);
AI assembles a paper **by selecting from `v2_questions`** — never authoring questions.

**Prerequisites:** Phases 1–2 client + logging; the deterministic engine (already **Implemented**).

**Reused code:** `ExamService::drawRandomIds()` (answerable-preferring, subject/topic/year-scoped
draw), `createFromQuestions()` (validates ids are active + have options + in-subject, caps at 40,
shuffles, freezes), `freeze()` (version-pinned snapshot into `v2_exam_questions`). The AI returns
ordered ids; the service persists. Contract + rules:
[03/D](./03-ai-context-contracts.md#d-ai-exam-generator), rule 7.

**Risks:**
- *Inventing/hallucinating questions* — hard-blocked by rule 7 and the engine's id-existence filter;
  the AI must only choose from real ids.
- *Ignoring per-topic counts / difficulty mix* — validate the AI's selection against the request
  before freezing; reject and retry deterministically on mismatch.
- *Answerless questions* — the engine already prefers `correct_answer IS NOT NULL`; keep that.

---

## Phase 4 — Notes AI

**Goal:** summarise a page, simplify a selection, generate flashcards/memcards/quiz — output saved
as **new AI-authored blocks** kept separate from imported source-of-truth blocks.

**Prerequisites:** Phases 1–2 client + logging; the Notes block infrastructure (**Implemented**).

**Reused code:** `NotesDocumentService::flattenText()` (input text), `NotesBlockMapper` (block
factory), `NotesImportController` insertion pattern (`source`-stamped new blocks — add an `ai`
origin), `NotesDocumentService::apply()` (search-field recompute + version snapshot). Contract:
[03/C](./03-ai-context-contracts.md#c-notes-ai), rule 9.

**Risks:**
- *Overwriting the student's own notes* — never; always insert new blocks (rule 9).
- *Confabulating beyond the notes* — ground output only in the provided `selection_text` (rule 5).
- *Block-shape mismatch* — the AI output must conform to the target BlockNote block schema; validate
  before splicing.

---

## Phase 5 — Retrieval / Embeddings

**Goal:** move from single-question grounding to **curriculum-grounded retrieval** — embed syllabus
topic/subtopic summaries, worked solutions, the student's notes, and mistake patterns; do vector
search so the tutor can teach a *topic* (not just one question) while staying on-curriculum.

**Prerequisites:** Phases 1–4; an embeddings store (evaluate pgvector / a vector service);
content to embed (syllabus hierarchy `v2_topics`/`v2_subtopics`, visible `QuestionLearningAsset`
content, `v2_notes_pages.plain_text`, mistake distributions).

**Reused code:** `NotesDocumentService::flattenText()` (note text), `visibleAsset()` (trusted content),
the topic/subtopic hierarchy tables. RAG keeps generation on-curriculum and prevents scope creep.

**Risks:** retrieval leaking cross-tenant content (embed per-tenant / filter by scope at query time,
rule 2/3); stale embeddings after content edits; cost of re-embedding at scale.

---

## Future moat — Cross-cohort misconception analytics

**Status: future.** This is a documented product goal (see
[../modules/12-mistake-bank.md](../modules/12-mistake-bank.md) "Misconception clustering" and the
project roadmap notes), not on the near-term build path.

- **Signal that already exists:** `v2_exam_answers.selected_option` records the *specific distractor*
  each student chose; at cohort scale this reveals systematic misconceptions
  ("60% who missed this picked C").
- **Missing piece (the moat):** a one-time, reusable **distractor taxonomy** — an offline batch pass
  (like the existing topic-tagging command) labelling each wrong option with the misconception it
  represents. Once questions carry that, per-class/school/subtopic misconception maps are cheap
  aggregation, and the tutor (Phase 1/5) can teach *against the student's specific misconception*
  rather than just the subtopic.
- **Caveat:** many science MCQs use image-only options (`option.text = ""`), so text-based distractor
  inference is partial — the taxonomy pass must handle image options.
- **Altitude rule:** misconception inference is noise on an *individual* report but signal at
  *cohort/population* scale — build it into cohort analytics, not the per-student narrative.

This layer is the connective tissue between cohort analytics (Phase 2) and a truly targeted tutor
(Phases 1/5): tutor teaches → generates targeted practice → results feed the behavioural layer →
sharpens what/how it teaches next.

---

## Phase / readiness at a glance

| Phase | Feature | Depends on | Reuses | Status |
|---|---|---|---|---|
| 0 (cross-cutting) | AI client (Claude) + `ai_interactions` logging | — | `services.*` config, `AuditLogger`/`v2_audit_logs` pattern | **Not implemented** (build first) |
| 1 | Student AI Tutor V1 | Phase 0 | `studio()`/`review()`, `visibleAsset()`, Notes bridge, `ReportService` shape | **Not implemented** — recommended next |
| 2 | AI Reports V1 (student/class/branch-wide/school) | Phase 0–1 | `ReportService`, `ExamService` + `PlatformStatsService` aggregates | **Partially implemented** (branch-per-student live) |
| 3 | AI Exam Generator | Phase 0–1 | `drawRandomIds`/`createFromQuestions`/`freeze` | **Not implemented** (engine exists) |
| 4 | Notes AI | Phase 0–1 | `flattenText`, `NotesBlockMapper`, import bridge | **Not implemented** |
| 5 | Retrieval / Embeddings | Phase 0–4 | topic hierarchy, notes text, visible assets | **future** |
| Moat | Cross-cohort misconception analytics | distractor taxonomy pass | `selected_option`, option text | **future** |
