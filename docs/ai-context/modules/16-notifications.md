# 16 — Notifications

> Code-grounded reference. Verify against source before acting.

**Siblings:** [17 — Secure Images](./17-secure-images.md) ·
[18 — Import & Data Pipeline](./18-import-and-data-pipeline.md) ·
[19 — Audit Logging](./19-audit-logging.md)

---

## Purpose

In-app notification system for the V2 platform. A single polymorphic table
(`v2_notifications`) drives two independent recipient experiences:

- **Teachers** get operational **updates** (exam scheduled/today, results due,
  a student flagged a question, quality-review outcomes, propagation fan-out)
  **and** persistent **attention** cases ("student X is weak in subject Y").
- **Students** get **update**-category exam-lifecycle alerts only (new/scheduled
  exam, due today, results released, missed).

**Status: Implemented in code** (teacher + student surfaces both wired). The
attention/weak-topic signal is the data-grounded piece an AI report layer could
reuse (see [AI Opportunities](#ai-opportunities)).

---

## Users / Roles

| Role | Category received | Notifiable model |
| --- | --- | --- |
| Teacher | `update` + `attention` | `App\Models\V2\Teacher` |
| Student | `update` only | `App\Models\V2\Student` |

The table's `notifiable_type` is polymorphic and the migration comment notes
"Teacher today; Student/BranchAdmin later." As of this reference, **only Teacher
and Student rows are written** — no super-admin, school-admin, or branch-admin
notifications exist in code.

---

## Current Implementation

**Write-side** — `app/Services/V2/NotificationService.php`. All creation goes
through this service; there is no generic `Notification::notify()`. Reads are
done directly off the `Notification` model in the Livewire bells/pages (see the
service's own header comment).

Key methods (verify signatures in the file):

- `teachersForStudentSubject(Student, int $subjectId): Collection` — resolves the
  teacher(s) of the student's class **for that subject** (`v2_classes.subject_id`
  via `v2_class_teachers` + `v2_student_enrollments`), preferring `is_primary`.
  Subject-scoped so the chemistry teacher never receives physics flags.
- `attentionKey(int $studentId, int $subjectId): string` → `"attention:{s}:{subj}"`
  — the stable `dedupe_key` for one student/subject attention case.
- `flagAttentionSubject(...)` — create/refresh **one** attention flag per
  (student, subject) listing every currently-weak topic + its weak subtopics.
  Preserves each topic's original `flagged` baseline across runs; **never** touches
  `read_at`/`resolved_at` (no re-nagging, never re-opens a handled flag). Returns
  `true` only on first create. Type written: `student_attention`.
- `resolveSubjectFlag(...)` — sets `resolved_at = now()` when a subject has no weak
  topic left (student ≥ `TARGET`). `TARGET = 50` (percent accuracy).
- `pushUpdate(int $teacherId, ..., string $type, string $dedupeKey, array $data)` —
  idempotent teacher **update** (updateOrCreate by dedupe key).
- `notifyStudent(...)` — idempotent student **update**.
- `notifyExamToStudents(Exam, $type, $dedupeKey, $body, ?$onlyStudentIds, ?$url)` —
  fans one exam update to enrolled students (optionally a subset, e.g. unattempted).
- `announceExamRelease(Exam)` / `announceResults(Exam)` / `announceScoreAdjusted(Exam, $studentIds)`
  — **real-time** student hooks fired from teacher release actions.
- `notifyTeachersOfStudentFlag(...)` — a student flagged a question → one
  notification per (exam, question) to the exam's teacher(s); running count so
  repeat reports bump instead of spamming. Type `question_flag`. (Links
  [module 19 audit](./19-audit-logging.md) via the flag pipeline.)
- `notifyReviewDecision(QualityReview, $outcome)` — quality-review outcome to the
  reporting teacher(s) only. Type `quality_review`.
- `notifyTeachersOfPropagation(Exam, $questionPos, $propagationId)` — Phase-4
  material-error fan-out. Type `quality_propagation`.

> **Note:** the prompt's expected `type` list (`exam_released`, `exam_open`,
> `flagged_attention_subject`, `results_due`, etc.) is **approximately** right but
> not literal. See the authoritative enum in [Data Model](#data-model) below —
> derived from the actual strings written by the service/commands. The migration's
> inline comment says `student_attention | exam_today | results_due | submissions`
> but `submissions` is **not written anywhere in code** (dead/aspirational value).

**Daily generators (no external API — pure DB):**

- `app/Console/Commands/V2/TeacherAttentionReminders.php` — `v2:teacher-attention-reminders`
  - Options: `--days=60`, `--min-tests=4`, `--min-questions=5`.
  - **Attention pass:** finds students with ≥ `min-tests` submitted tests in the
    window, computes `ExamService::studentStats($student, $since)`, and for each
    subject flags every topic with `total ≥ min-questions` **and** `percent <
    TARGET(50)`, worst-first, capped at 5. Writes/refreshes via
    `flagAttentionSubject`; auto-resolves via `resolveSubjectFlag` when no weak
    topic remains. Idempotent.
  - **Updates pass:** exams scheduled for a future date (`exam_scheduled`), exams
    opening today (`exam_today`), and windows closed with results unreleased
    (`results_due`, to the creator).
- `app/Console/Commands/V2/StudentExamReminders.php` — `v2:student-exam-reminders`
  (no options). Three date-driven passes: (1) scheduled exam opening today →
  `exam_released`; (2) live exam closing today, unattempted → `exam_due_today`;
  (3) released exam past its window, never attempted → `exam_missed`. Only the
  unattempted subset is notified (`unattempted(Exam)` helper).

**Scheduler** — `routes/console.php`:

```php
Schedule::command('v2:teacher-attention-reminders')->dailyAt('06:00');
Schedule::command('v2:student-exam-reminders')->dailyAt('06:05');
```

**Read-side UI (Livewire):**

- `app/Livewire/Teacher/NotificationBell.php` — header bell; caps each tab at 8;
  scopes to `Teacher::class` + `Auth::guard('v2_teacher')->id()`. Attention tab
  ordered worst-first by `JSON_EXTRACT(data,'$.current_avg')`. Actions:
  `markAllRead`, `markRead`, `markHandled` (sets `resolved_at`), `snooze` (+7 days).
- `app/Livewire/Student/NotificationBell.php` — header bell; preview of latest 10;
  scopes to `Student::class` + `Auth::guard('v2_student')->id()`. `open($id)` marks
  read and redirects to `data['url']` if present.
- `app/Livewire/{Teacher,Student}/NotificationIndex.php` — full pages.
- Views: `resources/views/v2/{teacher,student}/notifications.blade.php`.

**Resolution ownership:** an attention case is resolved by the teacher clicking
"handled" (`markHandled` → `resolved_at`) **or** by the daily generator when the
student improves (`resolveSubjectFlag`). The generator never re-opens a
teacher-handled flag (guarded by the `resolved_at` check in `flagAttentionSubject`).

---

## Data Model

**Table `v2_notifications`** — `database/migrations/2026_06_23_000001_create_v2_notifications_table.php`.
Model `app/Models/V2/Notification.php` (uses `HasHashid` route key).

| Column | Notes |
| --- | --- |
| `id` | PK |
| `school_id` | FK → `v2_schools`, cascade delete |
| `notifiable_type` / `notifiable_id` | polymorphic recipient (`morphTo`) |
| `category` (string 20) | `update` \| `attention` |
| `type` (string 40) | see enum below |
| `student_id` | nullable FK → `v2_students` (attention context / subject-scoped student updates) |
| `subject_id` | nullable FK → `v2_subjects` |
| `dedupe_key` | stable key; **unique** per `(notifiable_type, notifiable_id, dedupe_key)` |
| `data` (json) | display + trend payload (title, body, topics[], flagged/current %, target, url…) |
| `read_at` | bell unread state |
| `resolved_at` | attention auto-resolve / teacher "handled" |
| `snoozed_until` | snooze (teacher bell sets +7 days) |
| `created_at` / `updated_at` | timestamps |

Indexes: `(notifiable_type, notifiable_id, read_at)`,
`(notifiable_type, notifiable_id, resolved_at)`, unique `(notifiable_type,
notifiable_id, dedupe_key)`.

**`type` enum — values actually written in code** (grep the service + commands):

| `type` | category | Written by |
| --- | --- | --- |
| `student_attention` | attention | `flagAttentionSubject` |
| `exam_scheduled` | update | `announceExamRelease` (scheduled), `updatesPass` |
| `exam_released` | update | `announceExamRelease`, `StudentExamReminders` (opens today) |
| `exam_today` | update | teacher `updatesPass` |
| `exam_due_today` | update | `StudentExamReminders` |
| `exam_missed` | update | `StudentExamReminders` |
| `results_released` | update | `announceResults`, `announceScoreAdjusted` |
| `results_due` | update | teacher `updatesPass` |
| `question_flag` | update | `notifyTeachersOfStudentFlag` |
| `quality_review` | update | `notifyReviewDecision` |
| `quality_propagation` | update | `notifyTeachersOfPropagation` |

> `submissions` (in the migration comment) and `flagged_attention_subject` /
> `exam_open` (prompt guesses) are **not** literal code values — `unclear from
> codebase` whether they were ever intended.

**Model scopes:** `unread()` (`read_at` null), `attention()`, `updates()`,
`active()` (not resolved **and** not currently snoozed). Relations: `notifiable()`
(morphTo), `student()`, `subject()`, `school()`. Helper `isResolved()`.

---

## Core Flows

1. **Weak-topic attention (teacher).** Daily `v2:teacher-attention-reminders` →
   per eligible student → `studentStats` → weak topics → `flagAttentionSubject`
   (one row per student/subject, refreshed in place). Teacher sees it in the
   attention tab; clicks "handled" or the student improves → resolved.
2. **Exam released (real-time, student).** Teacher release action →
   `announceExamRelease(Exam)` → `exam_released`/`exam_scheduled` to enrolled
   students immediately.
3. **Date-driven exam reminders (student).** Daily `v2:student-exam-reminders`
   fills in opens-today / due-today / missed that the release hook can't fire.
4. **Results released (student).** `announceResults(Exam)` → `results_released`.
5. **Score adjusted by a void (student).** `announceScoreAdjusted(Exam, $ids)` →
   only affected students, `results_released` type, dedupe `score_adjusted:{examId}`.
6. **Student flags a question (teacher).** `notifyTeachersOfStudentFlag` →
   deep-link into the manage page's flagged row; count bumps on repeat reports.
7. **Quality review / propagation (teacher).** `notifyReviewDecision` /
   `notifyTeachersOfPropagation`.

---

## Inputs

- Real-time: teacher release/void/flag/review actions (controllers/services).
- Scheduled: two daily artisan commands (06:00 / 06:05).
- Analytics source for attention: `ExamService::studentStats()` (topic/subtopic %),
  and enrollment/class-teacher join tables.

## Outputs

- Rows in `v2_notifications`.
- Rendered bells + index pages (Livewire).
- Deep-link URLs baked into `data['url']` (routes resolved at write time).

---

## Dependencies

- `App\Services\V2\ExamService` (`studentStats`) — attention topic percentages.
- `App\Models\V2\{Exam, Student, Teacher, Subject, School, QualityReview, QuestionFlag}`.
- Tables `v2_class_teachers`, `v2_student_enrollments`, `v2_classes`,
  `v2_exam_attempts`, `v2_exam_questions`.
- `HasHashid` / `hid()` for route keys and deep links.

---

## Security / Access Rules

- Every read query is **hard-scoped** to the authenticated notifiable
  (`notifiable_type` + guard id) in both bells — no cross-user leakage.
- Attention flags are **subject-scoped**: only the teacher(s) of the student's
  class for that subject receive them.
- `data['url']` links point at role-appropriate routes; the destination pages
  enforce their own authorization.

---

## Existing AI-Relevant Context

- The **attention payload** (`data['topics']` with `flagged`/`current` percentages,
  weak subtopics, `target`, `current_avg`) is a **structured, data-grounded
  weakness signal** already computed and stored per student/subject.
- `dedupe_key` + `resolved_at` give a clean lifecycle a generated summary could
  attach to without re-computing.

## AI Opportunities

- **Recommended for AI:** natural-language weekly digests for teachers/parents,
  built from the existing attention rows (no new analytics needed — reuse
  `data['topics']`). Keep generation out of the write path; render on read.
- **Recommended for AI:** prioritised "what to intervene on" ranking across a
  teacher's attention queue, grounded in `current_avg` + trend (`flagged` vs
  `current`).

## AI Risks

- The attention pass is **deterministic and explainable today** (thresholds
  `TARGET`, `min-tests`, `min-questions`). Any AI layer must **augment, not
  replace** it — a hallucinated weakness in a teacher notification is high-trust
  and high-harm. Keep the numeric flag as the source of truth; AI only phrases it.
- `data` is user-facing; never embed raw internal IDs or un-verified AI claims.

## Future Improvements

- **Not implemented:** notifications for super-admin / school-admin / branch-admin
  (table supports it polymorphically; no writers exist).
- **Not implemented / unclear:** the `submissions` type in the migration comment.
- Attention currently refreshes but never expires stale-but-unhandled cases; a TTL
  or digest rollup is a candidate.
