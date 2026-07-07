# 03 — AI Context Contracts

> Forward-looking AI design doc. Grounded in current code; features described here are NOT yet built unless labelled **Implemented**.

**Cross-links:** [01 — Existing AI Usage](./01-existing-ai-usage.md) ·
[02 — AI Integration Opportunities](./02-ai-integration-opportunities.md) ·
[04 — AI Safety Rules](./04-ai-safety-rules.md) ·
[05 — AI Roadmap](./05-ai-roadmap.md) ·
Modules: [../modules/12-mistake-bank.md](../modules/12-mistake-bank.md) ·
[../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md) ·
[../modules/15-notes-module.md](../modules/15-notes-module.md) ·
[../modules/17-secure-images.md](../modules/17-secure-images.md) (see also `docs/modules/10-secure-imaging.md`)

---

## Purpose & conventions

This file defines the **exact context payload** each AI feature receives — as JSON-ish schemas with
`field → source table/service`. A future agent should be able to assemble these payloads directly
from the cited code.

Conventions used below:

- `field → Model::method()` or `→ v2_table.column` names the source of truth for a field.
- **Every payload is provider-agnostic.** Recommended provider for new AI = **Claude / Anthropic**
  (see [01](./01-existing-ai-usage.md)); shape it as a system message (persona + rules) + a user
  message (JSON context), and request structured JSON output — mirroring the existing
  `ReportService` OpenAI call (`app/Services/V2/ReportService.php:84–92`).
- **Diagram images are NEVER sent as URLs or bytes to the model.** They are passed as a *reference*
  `{ question_id, image_path }`. If a diagram must be rendered to a human, the app mints a
  viewer-bound signed URL at render time via `SignedImage::url($path, ['question_id' => $qid])`
  (`app/Support/SignedImage.php:19`) — see [../modules/17-secure-images.md](../modules/17-secure-images.md).
- Every contract MUST honour the safety rules in [04](./04-ai-safety-rules.md): ownership, school
  tenancy, release-gating, grounding, minimal PII, and separate-storage of outputs.

---

## A. Student AI Tutor

**Trigger surfaces (Phase 1):** the "Worked solution" studio for a mistake
(`LearningHubController::studio()`, `app/Http/Controllers/V2/Student/LearningHubController.php:90`)
and the mistake review page (`review()`, line 52). Context is the **current question/mistake only**.

**Preconditions (enforced before assembling context):**
- Ownership: `mistake.student_id === auth('v2_student')->id` (`authorizeMistake()`, controller line 210).
- Release gate: `mistake.latestExam && mistake.latestExam.resultsReleased()` (controller lines 57, 93;
  `Exam::resultsReleased()` = `results_released_at !== null`, `app/Models/V2/Exam.php:137`).
- School isolation: the `StudentMistake` global scope (`StudentMistake::booted()`, `app/Models/V2/StudentMistake.php:56`).

### Context payload

```jsonc
{
  "role": "student",                       // caller guard: v2_student. Never elevate.
  "student": {
    "first_name": "Ali"                    // strtok($student->name,' ') — FIRST NAME ONLY (PII rule 11)
                                           // → v2_students.name; do NOT send surname/email/roll/id
  },
  "grade_class": {                         // OPTIONAL context, not identity
    "grade": "A2",                         // → v2_grades.name via student's active class (studentDashboard)
    "class": "A2 Physics — Sec A"          // → v2_classes.name; omit if not needed
  },
  "curriculum": {                          // ALL denormalised on the mistake row (join-free)
    "subject":  "Physics",                 // → StudentMistake::subject()->name  (v2_student_mistakes.subject_id)
    "topic":    "Kinematics",              // → StudentMistake::topic()->title
    "subtopic": "Projectile motion"        // → StudentMistake::subtopic()->title (v2_student_mistakes.subtopic_id)
  },
  "question": {
    "question_id": 12345,                  // → v2_student_mistakes.question_id
    "stem": "A ball is projected …",       // → Question::question_text (studio() loads via withoutGlobalScopes)
    "options": [                           // → Question::options (v2_question_options: label,text)
      {"label":"A","text":"…"}, {"label":"B","text":"…"},
      {"label":"C","text":"…"}, {"label":"D","text":"…"}
    ],
    "correct_answer": "C",                 // → Question::correct_answer (AUTHORITATIVE — never contradict, rule 1)
    "source_paper": "9702_s19_qp_12",      // → v2_student_mistakes.source_paper (denormalised)
    "diagram_reference": {                 // REFERENCE ONLY — never a URL/bytes to the model (rule + module 17)
      "question_id": 12345,
      "image_path": "questions/9702/…png"  // → v2_question_images.image_path (role ~ question/diagram/figure, option_label IS NULL)
    }
  },
  "student_answer": {
    "selected_option": "B",                // → v2_student_mistakes.selected_option (their latest wrong answer)
    "is_correct": false                    // by definition a mistake row
  },
  "grounding_assets": {                    // TRUSTED, super-admin-approved content ONLY (rule 4)
                                           // each via MistakeBankService::visibleAsset(question_id, <type>)
                                           // which returns only VISIBLE_STATUSES = [approved, edited]
    "worked_solution":     { "title":"…", "content":"…markdown…" }, // asset_type worked_solution
    "option_explanations": { "content":"why each distractor is wrong" }, // asset_type option_explanation
    "flashcards":          { "payload": [ /* front/back */ ] },     // asset_type flashcards (payload_json)
    "memcards":            { "payload": [ /* recall cards */ ] },   // asset_type memcards
    "mermaid":             { "content":"graph TD; …" },             // asset_type mermaid
    "interactive_widget":  { "type":"…", "config":{…} }             // asset_type interactive_widget (payload_json)
    // Omit any asset that has no visible row. If NONE exist → tell the model so, and it must lean on
    // stem+options+correct_answer only, saying when it lacks material (rule 5).
  },
  "behaviour": {                           // this student's history with THIS question
    "mistake_count":  3,                   // → v2_student_mistakes.mistake_count
    "mastery_status": "reviewed",          // → v2_student_mistakes.status (new|reviewed|practiced|mastered|archived)
    "review_count":   2,                   // → v2_student_mistakes.review_count
    "first_wrong_at": "2026-05-01",        // → v2_student_mistakes.first_wrong_at
    "last_wrong_at":  "2026-06-20"         // → v2_student_mistakes.last_wrong_at
  },
  "recent_context": {                      // OPTIONAL broader signal (Phase 1 may omit)
    "recent_attempts": [ /* last N */ ],   // → ExamService::studentScoreTrend(studentId, N)
    "weak_topics":     [ /* topic:%  */ ]  // → ExamService::studentStats()/studentTopicStats() — subject-scoped
  },
  "related_notes": null,                   // OPTIONAL & only if safe: the student's OWN note text for this
                                           // topic (→ v2_notes_pages.plain_text via NotesDocumentService).
                                           // Never another student's notes. Omit unless clearly useful.
  "answer_style": {                        // instructions to the model (system message)
    "persona": "encouraging Cambridge O/A-Level tutor, plain language",
    "rules": [
      "Ground every claim in the provided stem, options, correct_answer, and grounding_assets.",
      "The correct_answer is authoritative — never contradict it or invent a different key.",
      "Explain why the student's selected_option is wrong and why correct_answer is right.",
      "Do not invent syllabus facts, formulae, or numbers not supported by the context.",
      "If grounding material is thin, say what you can and state the limitation."
    ]
  }
}
```

**Where these are already assembled:** `LearningHubController::studio()` already loads the question
(with images), resolves each `visibleAsset()`, builds the signed figure URLs, and knows the
mistake's subject/topic/selected_option/correct answer (lines 95–166). The tutor endpoint should
reuse that assembly and pass the **reference** (`question_id` + `image_path`) into the payload rather
than the signed URL.

**Output → Notes (Phase 1 save target):** the model's explanation is saved as a **new AI-authored
block** appended to a Notes page via the existing import bridge pattern
(`NotesImportController`, `NotesBlockMapper`), anchored to the mistake — see
[C. Notes AI](#c-notes-ai) and rule 9 in [04](./04-ai-safety-rules.md). It is never written back
onto the source-of-truth `QuestionLearningAsset` rows.

---

## B. Mistake Explanation AI ("why was my answer wrong?")

The **minimal grounded subset** of the tutor payload — a single button on a mistake, one turn, no
history, no notes. Same preconditions (ownership + release + school scope).

```jsonc
{
  "role": "student",
  "student":  { "first_name": "Ali" },                 // FIRST NAME ONLY
  "question": {
    "question_id": 12345,
    "stem": "…",                                        // → Question::question_text
    "options": [ {"label":"A","text":"…"}, … ],         // → Question::options
    "correct_answer": "C",                              // → Question::correct_answer (authoritative)
    "diagram_reference": { "question_id":12345, "image_path":"…" } // reference only
  },
  "student_answer": { "selected_option": "B" },         // → v2_student_mistakes.selected_option
  "grounding": {
    "worked_solution":     "…",   // visibleAsset(qid,'worked_solution')  — omit if absent
    "option_explanations": "…"    // visibleAsset(qid,'option_explanation') — omit if absent
  },
  "ask": "Explain in 2–4 sentences why B is wrong and why C is correct. Ground it in the provided material; if there is no worked solution, reason only from the stem and options and say so."
}
```

If neither grounding asset exists, the model must reason from stem + options + correct_answer and
**say the explanation is unassisted** (rule 5). It must never contradict `correct_answer` (rule 1).

---

## C. Notes AI

Operates on the student's **own** Notes page (`v2_notes_pages`, guard `v2_student`, scoped to
`student_id`). See [../modules/15-notes-module.md](../modules/15-notes-module.md).

**Operations & their input context:**

| Operation | Input context (source) | Output |
|---|---|---|
| Summarise page | `page.plain_text` (`NotesDocumentService::flattenText(document_json)`) + `page.title` | new AI summary block(s) |
| Simplify selection | the selected block(s)' text (client sends the block subtree) | new simplified block(s) |
| Generate flashcards | page/selection text | AI-authored flashcard blocks (BlockNote `flashcard`/card blocks — see NotesBlockMapper) |
| Generate memcards | page/selection text | AI-authored memcard blocks |
| Create quiz | page/selection text | AI-authored quiz block(s) |

```jsonc
{
  "role": "student",
  "student": { "first_name": "Ali" },
  "page": {
    "page_id":  "hashid",                 // → v2_notes_pages route key (HasHashid)
    "title":    "Kinematics summary",     // → v2_notes_pages.title
    "subject":  "Physics",                // → notebook.subject_id (NotesNotebook)
    "topic":    "Kinematics"              // → section.topic_id (NotesSection)
  },
  "selection_text": "…flattened text of selection or whole page…", // → NotesDocumentService::flattenText()
  "operation": "generate_flashcards",     // one of: summarise | simplify | flashcards | memcards | quiz
  "answer_style": {
    "persona": "study assistant; concise, exam-focused",
    "rules": [
      "Base output ONLY on the provided selection_text.",
      "Do not invent facts beyond the student's notes.",
      "Output must fit the requested block type."
    ]
  }
}
```

### Save target (CRITICAL rule)

AI output is saved as **new AI-authored block(s)**, inserted via the same mechanism as the existing
"add to notes" import bridge (`app/Http/Controllers/V2/Student/NotesImportController.php`;
`NotesDocumentService::apply()` re-computes search fields + snapshots a version). The AI blocks are
kept **SEPARATE** from imported source-of-truth blocks (worked-solution / question-figure blocks
that came from the platform). Recommended: tag AI blocks (e.g. a block prop `origin: "ai"`) so they
are visually and structurally distinguishable and never overwrite imported content. This mirrors how
the import bridge already stamps `source ∈ {mistake, asset, widget_state}`
(`NotesImportController` validation) — add an `ai` origin rather than mutating existing blocks. See
rule 9 in [04](./04-ai-safety-rules.md).

---

## D. AI Exam Generator

Teacher-facing (guard `v2_teacher`). The AI's role is **selection & orchestration over the existing
bank** — it does NOT author questions. The deterministic engine already exists:
`ExamService::generate()` / `drawRandomIds()` / `createFromQuestions()` / `freeze()`
(`app/Services/V2/ExamService.php:30–159`).

```jsonc
{
  "role": "teacher",
  "teacher": { "id": 42, "name": "Ms Khan" },       // → v2_teachers (name optional; id required for scope)
  "scope": {
    "school_id": 7,                                  // → teacher.school_id (freeze uses this)
    "branch_id": 3,                                  // → teacher.branch_id
    "class_id":  88,                                 // → v2_classes.id (SchoolClass::findOrFail)
    "subject_id": 5,                                 // → v2_classes.subject_id (draw is scoped to this)
    "grade":      "A2"                               // → v2_grades.name (context)
  },
  "selection": {
    "topic_ids":    [11, 12],                        // → v2_topics.id (drawRandomIds $topicIds)
    "subtopic_ids": [101, 102],                      // → v2_subtopics.id (OPTIONAL finer filter)
    "per_topic_counts": { "11": 6, "12": 4 },        // teacher-specified counts PER topic
    "difficulty_distribution": { "easy":3, "medium":5, "hard":2 }, // → v2_questions.difficulty
    "question_count": 10,                            // total; capped at 40 by the engine
    "duration_minutes": 30,                          // → v2_exams.duration_minutes
    "year_from": 2015, "year_to": 2023,              // → drawRandomIds $yearFrom/$yearTo (v2_questions.year)
    "bank_filters": { "answerable_only": true }      // engine already prefers correct_answer IS NOT NULL
  },
  "constraints": {                                   // HARD RULES — see rule 7 (04)
    "must_draw_from": "v2_questions",                // only real, active, has-options questions
    "obey_topics_and_counts": true,                  // respect teacher-selected topics & per-topic counts exactly
    "allow_unsupported_random_fill": false           // do NOT invent/random-fill beyond selection unless TRUE
  }
}
```

**Rules the generator MUST obey (rule 7 in [04](./04-ai-safety-rules.md)):**

1. Every question id in the output must exist in `v2_questions`, be `active()`, and `has('options')`
   — exactly the filter `drawRandomIds()`/`createFromQuestions()` already enforce
   (`ExamService.php:66–73, 99–102`).
2. Obey teacher-selected topics and per-topic counts. Do not silently substitute topics.
3. Do **not** invent questions or pull random unsupported questions unless
   `allow_unsupported_random_fill` is explicitly true (mirrors the engine's answerable-preferring +
   optional fill behaviour).
4. Selection only; **freezing** (versioned snapshot into `v2_exam_questions`) stays with
   `ExamService::freeze()` — the AI returns ids, the service persists them.

**Output:** an ordered list of `question_id`s (+ per-topic mapping) handed to
`ExamService::createFromQuestions()` (or a thin AI wrapper around `drawRandomIds`), so freezing,
capping at 40, and version pinning behave identically to a human-built exam.

---

## E. AI Report Generator

**One generator, five scoped contracts.** All follow the `ReportService` precedent
(`app/Services/V2/ReportService.php`): ground the narrative in real stats, send minimal PII, always
have a deterministic fallback, request structured JSON.

Shared output shape (from the live feature): `{ source, summary, subjects|sections }` where
`summary` is 2–3 sentences and each section is a 2–3 sentence comment. Persist to a report row's
JSON payload (precedent: `v2_reports.payload` + `v2_reports.source`).

### E1. Student report (self)

- **Privacy scope:** the student's own data only (guard `v2_student`, `student_id`).
- **PII:** first name only.
- **Raw stats:** `ExamService::studentStats($student, $since, $until)` — shape:
  ```jsonc
  {
    "overall": { "tests": 12, "avg": 71 },           // avg % across submitted attempts in window
    "subjects": [ {
      "subject_id": 5, "subject": "Physics",
      "tests_count": 6, "avg": 68,
      "topics": [ {
        "topic": "Kinematics", "correct": 8, "total": 12, "percent": 67,
        "subtopics": [ { "subtopic":"Projectiles", "correct":2,"total":5,"percent":40 } ]
      } ],
      "all_topics": [ "Kinematics", "Dynamics", … ]  // FULL syllabus topic list = grounding vocabulary
    } ]
  }
  ```
  (Verbatim from `ExamService::studentStats()`, `ExamService.php:433–541`.)
- **Trend / comparison:** `studentScoreTrend()`, `studentImprovement()`, `studentMonthlyActivity()`.
- **Weak / strong topics:** derive from `subjects[].topics[].percent` (lowest / highest).
- **Recommendations:** prerequisite topics chosen **only** from `all_topics` (the live grounding
  rule — never invent a topic; see [01](./01-existing-ai-usage.md) and rule 6 in [04](./04-ai-safety-rules.md)).
- **Tone:** encouraging, plain, student-facing.

### E2. Teacher-class report

- **Privacy scope:** the teacher's own classes/students only (guard `v2_teacher`).
- **PII:** student first names or roll numbers, never emails; aggregate where possible.
- **Raw stats:** `ExamService::classTopicStats($class)`, `classStudentMatrix($class)`
  (per-student × per-topic), `teacherStudentPerformance($teacherId)` (worst-first),
  `teacherScoreDistribution($teacherId)`, `teacherExamTrend($teacherId)`, `teacherOverview($teacherId)`.
- **Weak / strong topics:** class-level from `classTopicStats`. **Who needs attention:** worst rows
  of `teacherStudentPerformance` / `classStudentMatrix`.
- **Recommendations:** which topics to reteach; which students to support. Ground in the matrix.
- **Tone:** professional, actionable, teacher-facing.

### E3. Subject / topic / subtopic report

- **Privacy scope:** the requesting role's tenant (teacher→own classes; admin→school/branch).
- **Raw stats:** per-topic/subtopic accuracy from `studentStats()` (individual) or
  `classTopicStats()` / `schoolTopicStats($schoolId,$branchId)` (aggregate); platform-wide
  `PlatformStatsService::hardestQuestions()` for the super-admin cross-school view.
- **Trend:** `teacherExamTrend()` / `PlatformStatsService::dailyActivity()`.
- **Recommendations:** curriculum-level focus areas grounded in the aggregate.

### E4. Branch-admin report

- **Per-student:** the **live** feature — `ReportService::build()` via
  `BranchAdmin/ReportController`. **Implemented.**
- **Branch-wide rollup (not built):** scope = one `branch_id` within the admin's school.
  Raw stats: `schoolOverview($schoolId,$branchId)`, `schoolClassRows(…, $branchId)`,
  `schoolTeacherRows(…, $branchId)`, `schoolTopicStats(…, $branchId)`, `schoolBranchRows($schoolId)`.
  **PII:** aggregate; name classes/teachers, not individual students unless drilling into one.

### E5. School-admin report

- **Privacy scope:** one `school_id` (guard `v2_school_admin`). Never cross-school (that is
  super-admin territory; `PlatformStatsService` is the only cross-school surface and is deliberately
  raw/unscoped for the super admin).
- **Raw stats:** `schoolOverview()`, `schoolBranchRows()`, `schoolClassRows()`, `schoolTeacherRows()`,
  `schoolTopicStats()`, plus grade/subject drill-downs in `ExamService`.
- **Comparison:** branch-vs-branch, class-vs-class from the `*Rows()` helpers.
- **Tone:** executive summary; aggregate only.

### E-N/A. Parent report

**N/A — there is NO parent role in the system.** The auth guards are exactly
`v2_super_admin`, `v2_school_admin`, `v2_branch_admin`, `v2_teacher`, `v2_student` (`config/auth.php`);
grepping the codebase for a parent role/guard/model returns nothing. Do not build a parent-scoped
report contract until a parent role exists. See `../roles/06-parent-not-implemented.md`.
(The live report's *tone* is described as "parent-friendly" in `ReportService`, but it is generated
for and delivered by the **branch admin**, not to a parent account.)
