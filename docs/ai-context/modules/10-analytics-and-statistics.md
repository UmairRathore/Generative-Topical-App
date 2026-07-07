# 10 — Analytics & Statistics

> Code-grounded reference. Verify against source before acting.

**Siblings:** [08 — Exam Builder](./08-exam-builder.md) ·
[09 — Exam Attempts / Checking / Results](./09-exam-attempts-checking-results.md) ·
[11 — Reports (current system)](./11-reports-current-system.md) ·
[12 — Mistake Bank](./12-mistake-bank.md)

---

## Purpose

All performance analytics — per student, class, teacher, subject, topic/subtopic, grade, branch,
school, and platform-wide. **There are NO dedicated analytics tables.** Everything is computed **live**
from `v2_exam_attempts` + `v2_exam_answers` + `v2_questions` (joined to topics/subtopics/subjects) on
each request. `App\Services\V2\ExamService` is the single analytics engine; `PlatformStatsService`
adds cross-school business/engagement metrics.

**This module is the raw material every AI report consumes.** The exact returned shapes are documented
below — future AI must treat them as the contract.

Status: **Implemented in code.**

## Users / Roles & Entry Points

| Role | Controller | Scope |
|---|---|---|
| Super Admin | `SuperAdmin/StatsController` (+ `SuperAdmin/StudentController`) | whole platform, **unscoped** |
| School Admin | `SchoolAdmin/AnalyticsController` | one school (drill-down grade→subject→topic→teacher→class→student→paper) |
| Branch Admin | `BranchAdmin/AnalyticsController` | one branch (same drill-down, narrowed by branch) |
| Teacher | `Teacher/StatsController` + `Teacher/ClassController` | their own exams / assigned classes / students |
| Student | `Student/StatsController` | their own history, self-scoped |

Routes: `routes/v2.php` — super `stats` (line 82); school `analytics.*` (161-171); branch `dashboard`/
drill-down (246-257); teacher `stats` (286) + `classes.*` (311+); student `stats` (340).

## Current Implementation

### The engine — `app/Services/V2/ExamService.php`
All analytics methods live here (the same class that builds/marks exams). **Every topic/subtopic
aggregation joins `v2_exam_questions` and filters `is_voided = false`** — the void pivot is the single
source of truth, so a voided question never pollutes a stat. Tenancy: Eloquent-based methods inherit
the model `school` global scope; the raw `DB::table` drill-down helpers are **explicitly keyed on ids**
(so the unscoped Super Admin can call them for any school).

Key method groups (see *Data shapes* for the crucial ones):

- **Student:** `studentStats()`, `studentDashboard()`, `studentScoreTrend()`, `studentTopicStats()`,
  `studentMonthlyActivity()`, `studentImprovement()`.
- **Per-attempt / per-exam:** `topicStatsForAttempt()`, `topicStatsForExam()`, `resultBreakdown()`
  (module 09).
- **Class:** `classTopicStats()`, `classStudentMatrix()`.
- **Teacher:** `teacherTopicStats()`, `teacherOverview()`, `teacherEngagement()`,
  `teacherScoreDistribution()`, `teacherExamTrend()`, `teacherStudentPerformance()`.
- **School / branch:** `schoolOverview()`, `schoolTopicStats()`, `schoolTopicStatsBySubject()`,
  `schoolTeacherRows()`, `schoolBranchRows()`, `schoolClassRows()`, `gradeWideRows()`,
  `gradeTopicStats()`, `subjectWideRows()`, `subjectTopicStats()`, `examKindSplit()`.
- **Platform (cross-school):** `topicWideStats()`, `topicWideStatsBySubject()`, `gradePlatformRows()`,
  `subjectPlatformRows()`.
- **Private shared engines:** `topicStats($query)` (lines 1427-1448) and `topicStatsBySubject($query)`
  (1286-1324) — take an already-scoped answers base query and return the `[{topic,correct,total,
  percent}]` / subject-grouped rollups; `schoolAnswersBase()` / `branchClassIds()` build the scoping.

### Platform business metrics — `app/Services/V2/PlatformStatsService.php`
All raw `DB::table` (no scope fires — Super Admin only). MRR/growth are **reconstructed from row
timestamps** (there is no billing ledger — best-effort trends, not accounting):
- `mrrTrend(months)` → `[{label, mrr}]` (cumulative monthly_fee of active/suspended schools from their
  start month).
- `growth(months)` → `{labels, schools[], teachers[], students[], exams[]}` (new rows/month).
- `dailyActivity(days)` → `[{label, count, weekend}]` (submitted attempts/day).
- `schoolEngagement()` → per active school `{id,name,fee,exams,reports,last_exam, risk:'high|medium|low'}`.
- `hardestQuestions(limit, minAttempts)` → lowest correct-rate questions platform-wide
  `[{source,qno,topic,subject,attempts,correct}]` (excludes voided).

### Views
Under each role's `*/analytics/` (or `*/stats/`) directory:
- School: `resources/views/v2/school_admin/analytics/` — `index`, `grades`, `grade`, `subjects`,
  `subject`, `topics`, `teacher`, `class`, `student`, `paper`.
- Branch: `resources/views/v2/branch_admin/analytics/` — same set (+ report generate/pdf, module 11).
- Teacher: `v2/teacher/stats/index`, `v2/teacher/classes/*`, `v2/teacher/students/show`.
- Student: `v2/student/stats/index`.
- Super Admin: `v2/super_admin/stats/index`, `v2/super_admin/students/*`.
- **Shared partials** (`resources/views/v2/partials/`): `student_matrix.blade.php`,
  `topic_bars.blade.php`, `result_palette.blade.php`, `result_score_strip.blade.php`,
  `answer_review.blade.php`, `chartjs.blade.php`.

### Migrations / Commands / Jobs / Tests
- **Migrations:** none — analytics own no tables (they read the exam tables from
  [modules 08](./08-exam-builder.md)/[09](./09-exam-attempts-checking-results.md)).
- **Commands/Jobs:** none for on-request analytics. (A daily teacher-attention reminder command exists
  separately — teacher-notifications memory note — but it is notification, not analytics rendering.)
- **Tests:** **None** covering these aggregations. Coverage gap; the SQL is intricate (multi-join void
  exclusion) and untested.

## Data Model
No analytics tables. Effective sources: `v2_exam_attempts.status='submitted'`, `v2_exam_answers.
is_correct`, joined to `v2_questions.(subject_id, topic_id, subtopic_id)` → `v2_topics` / `v2_subtopics`
/ `v2_subjects`, and **always** `v2_exam_questions.is_voided = false`.

## Data shapes (the AI contract — verify before consuming)

### `studentStats(Student, ?since, ?until, ?subjectIds): array` (lines 433-541)
The core per-student aggregate. `subjectIds` (e.g. a teacher who teaches only some subjects) scopes
every figure. Topics/subtopics only appear if they were actually tested in the window (`total > 0`).
```php
[
  'overall'  => ['tests' => int, 'avg' => int],   // avg = mean of attempt.percentage, 0 if none
  'subjects' => [
    [
      'subject_id'  => int,
      'subject'     => string,
      'tests_count' => int,
      'avg'         => int,                        // mean percentage across the subject's attempts
      'topics'      => [
        [
          'topic'     => string,                   // 'Untagged' if no topic
          'correct'   => int, 'total' => int, 'percent' => int,
          'subtopics' => [ ['subtopic'=>string,'correct'=>int,'total'=>int,'percent'=>int], ... ],
        ], ...
      ],
      'all_topics'  => [string, ...],  // EVERY syllabus topic title in the subject, in order —
                                       // the ONLY pool a narrative may recommend from (module 11)
      'tests'       => [ ['exam_id','title','topic','score','total','percent','date'], ... ],
    ], ...
  ],
]
```
Consumed verbatim by `ReportService::build()` ([module 11](./11-reports-current-system.md)), the
school/branch/teacher/super-admin student pages, and the student stats page.

### `studentDashboard(Student): array` (lines 555-750)
Richer, dashboard-oriented. Adds **missed** tests, **upcoming** exams, and **full syllabus topic
coverage** (examined / attempted / untouched) per subject. "Missed" = a released exam in the student's
class whose window closed and was never submitted.
```php
[
  'overall'  => ['completed'=>int,'missed'=>int,'total'=>int,'avg'=>int,'subjects'=>int],
  'subjects' => [
    [
      'subject_id','subject','teacher'(?string, comma-joined),
      'avg'=>int,'tests_count'=>int,'missed_count'=>int,
      'total_topics'=>int,'examined_topics'=>int,'attempted_topics'=>int,
      'topics' => [ ['topic'=>string,'examined'=>bool,'attempted'=>bool,
                     'correct'=>int,'total'=>int,'percent'=>int], ... ],  // ALL syllabus topics
      'tests'  => [ ['exam_id','title','score','total','percent','date','created_at','topics'[],'results'(bool)], ... ],
    ], ...
  ],
  'upcoming' => [ ['exam_id','title','subject','teacher','status'('live'|'scheduled'),
                   'available_from','due','questions','topics'[]], ... ],
]
```
Reused by `Student/ExamController::index` (subject headers) and `Student/StatsController`.

### `classStudentMatrix(SchoolClass): array` (lines 1048-1105)
Per-student × per-topic accuracy grid for a class.
```php
[
  'topics' => [string, ...],                       // column order
  'rows'   => [
    ['id','student','roll','overall'(?int),'attempted'(bool),
     'cells' => [ topic => ['correct'=>int,'total'=>int,'percent'=>int] | null ]],
    ...
  ],
]
```
Rendered by `student_matrix.blade.php`; used by teacher/school/branch class views.

### Topic rollups (shared shape)
`topicStats()` and its callers (`studentTopicStats`, `classTopicStats`, `teacherTopicStats`,
`schoolTopicStats`, `gradeTopicStats`, `subjectTopicStats`, `topicWideStats`, `topicStatsForAttempt`,
`topicStatsForExam`) all return `[{ 'topic'=>string, 'correct'=>int, 'total'=>int, 'percent'=>int }]`,
ordered by syllabus number, `'Untagged'` last.

`topicStatsBySubject()` / `schoolTopicStatsBySubject()` / `topicWideStatsBySubject()` return one block
per subject: `[{ subject, code, level, correct, total, percent, topics:[{topic,correct,total,percent}] }]`,
groups ordered by volume.

### Summary-row helpers
`schoolOverview`/`teacherOverview` → totals + `avg`. `schoolClassRows`/`gradeWideRows`/
`subjectWideRows`/`schoolTeacherRows`/`schoolBranchRows` → per-entity rows with `submissions` + `avg`.
`teacherScoreDistribution` → `int[5]` bins (0-20,21-40,41-60,61-80,81-100). `teacherExamTrend` →
`[{title,date,count,avg}]`. `teacherStudentPerformance` → worst-first `[{id,name,roll,attempts,avg}]`.
`studentScoreTrend` → `[{label,score,date,subject}]`. `studentMonthlyActivity` → `int[12]`.
`studentImprovement` → `?float` (last-5 avg − prior-5 avg). `examKindSplit` →
`['single'=>{exams,submissions,avg},'mixed'=>{...}]`.

## Core Flows
Request → role controller → one or more `ExamService`/`PlatformStatsService` methods → Blade + Chart.js
partials. No caching, no materialized tables — recomputed each load. Drill-down is pure route
navigation (grade → subject → teacher → class → student → single paper), each level calling the
matching scoped helper.

## Inputs
Role/guard (implicit scope) + route-model ids (`{grade}`, `{subject}`, `{teacher}`, `{class}`,
`{student}`, `{exam}`). Some helpers accept optional `branchId` / `since` / `until` / `subjectIds`.

## Outputs
The arrays above → chart/table Blade views. Nothing is persisted (except `v2_reports`, module 11).

## Dependencies
- Exam attempts/answers + question bank (topic/subtopic/subject tagging quality directly shapes every
  stat — untagged questions roll up as `'Untagged'`).
- Void pivot (`is_voided`) — enforced in every aggregation.
- Chart.js (client) via `chartjs.blade.php`.

## Security / Access Rules
- Model global `school` scope on `Exam`/`ExamAttempt` for Eloquent paths; explicit `abort_unless`
  ownership checks in every drill-down controller action (e.g. `teacher->school_id === schoolId`,
  `class->branch_id === branchId`, enrollment checks before a paper).
- Super Admin helpers are raw `DB::table` (unscoped by design) keyed on explicit ids.
- Branch admin `studentPaper` additionally asserts the exam's class is in **this** branch (Exam has no
  branch scope) — a documented sharp edge.

## Existing AI-Relevant Context
- **No AI in analytics computation** — all deterministic SQL/PHP.
- These shapes are **the ground-truth feature set** for AI. `studentStats` (with `all_topics` as the
  legal recommendation pool) is already consumed by the report narrative ([module 11](./11-reports-current-system.md)).
- `PlatformStatsService::hardestQuestions` and the per-topic/per-subtopic accuracy are exactly the
  cohort signals the behavioural-analytics roadmap wants to mine.

## AI Opportunities *(Recommended for AI — not implemented)*
- **Narrative over any rollup** (class/teacher/school), not just the single student report.
- **Weak-spot detection & prerequisite mapping** using `all_topics` + per-subtopic accuracy.
- **Cohort misconception analytics** from wrong-option distributions (needs distractor taxonomy).
- **Anomaly/at-risk flagging** on `teacherStudentPerformance` / `schoolEngagement`.

## AI Risks
- **Void + scope fidelity:** any AI query must replicate `is_voided = false` and the tenancy scoping,
  or it will report on removed questions or leak across schools/branches.
- **Untagged noise:** `'Untagged'` buckets can dominate; AI must not treat them as a real topic.
- **Small-N over-reading:** many `percent` values sit on tiny `total`s; AI must weight by `total`
  (the code exposes both `correct` and `total` precisely for this).
- **No history:** stats are recomputed live and voids rewrite scores — there is no time-series of past
  states, so AI must not assume stable historical numbers.

## Future Improvements
- Add tests for the void-exclusion joins and each shape (currently none).
- Consider caching/materializing hot rollups (everything is recomputed per request).
- A per-answer timestamp/order would unlock behavioural (not just accuracy) analytics.
