# 02 — School / Branch / Class Structure

> Code-grounded reference. Verify against source before acting.

**Siblings:** [01 — Roles, Permissions & Tenancy](./01-roles-permissions-tenancy.md) ·
[03 — Auth / Onboarding / Credentials](./03-auth-onboarding-credentials.md) ·
[00 — System Overview](../00-system-overview.md)

---

## Purpose

Defines the **organisational hierarchy** every V2 person and result hangs off:
`school → branch → grade / subject → class → enrolled students + assigned teachers`.
This is the tenancy skeleton — the `school_id` / `branch_id` keys that
[01 — Tenancy](./01-roles-permissions-tenancy.md) filters on all originate here.

Status: **Implemented in code** (tables, models, school-admin CRUD). Super-admin *creation* of
schools/branches is **Not implemented / future** (see Core Flows).

## Users / Roles

- **Super admin** — reads across all schools; drills down
  `school → branch → grade/teacher/subject/class/student/paper` (`routes/v2.php:92-107`). Cannot
  yet *create* a school through the UI (route is a view stub).
- **School admin** — full CRUD on grades, classes, teachers, students; assigns subjects and
  class-teachers within their own school (`routes/v2.php:173-220`).
- **Branch admin** — read-only analytics scoped to a single branch (`routes/v2.php:245-260`).
- **Teacher** — reads their classes/students for analytics (`routes/v2.php:311-314`).
- **Student** — belongs to classes via enrollment; no structural editing.

## Current Implementation

**Migrations** (`database/migrations/`):
- `2026_06_13_000002_create_v2_schools_table.php`
- `2026_06_18_000001_create_v2_branches_and_branch_admins.php` (creates `v2_branches` **and**
  `v2_branch_admins`; also **adds nullable `branch_id`** to `v2_teachers`, `v2_students`,
  `v2_classes`, `v2_student_enrollments`)
- `2026_06_13_000009_create_v2_grades_table.php`
- `2026_06_13_000010_create_v2_subjects_table.php`
- `2026_06_13_000011_create_v2_school_subjects_table.php`
- `2026_06_13_000012_create_v2_classes_table.php`
- `2026_06_13_000013_create_v2_class_teachers_table.php`
- `2026_06_13_000014_create_v2_student_enrollments_table.php`

**Models** (`app/Models/V2/`): `School.php`, `Branch.php`, `Grade.php`, `Subject.php`,
`SchoolSubject.php`, `SchoolClass.php` (table `v2_classes`), `ClassTeacher.php`,
`StudentEnrollment.php`, plus `Teacher.php` / `Student.php` (people).

**Controllers** (school-admin CRUD, `app/Http/Controllers/V2/SchoolAdmin/`):
`GradeController.php`, `SubjectController.php`, `ClassController.php`,
`TeacherController.php`, `StudentController.php` (all extend `BaseController.php`).

**Super-admin drill-down** (`app/Http/Controllers/V2/SuperAdmin/`): `SchoolController.php`,
`BranchController.php`, `GradeController.php`, `SubjectController.php`, `ClassController.php`,
`TeacherController.php`, `StudentController.php`, `TopicController.php`.

**Views** (`resources/views/v2/`): `school_admin/{grades,subjects,classes,teachers,students}`,
`super_admin/{schools,branches,grades,subjects,classes,teachers,students,topics}`.

## Data Model

Column facts below are read directly from the migration files listed above. `id`/timestamps
omitted for brevity except where notable.

### `v2_schools` — root tenant (`2026_06_13_000002…`)
Model `app/Models/V2/School.php`. **No tenancy scope** (it *is* the tenant).
- `name`, `campus_name` (nullable), `address` (text, nullable)
- `contact_email`, `contact_phone` (nullable)
- `monthly_fee` decimal(10,2) default 0; `license_tier` string default `standard`
- `max_teachers` int default 30; `max_students` int default 500 — **enforced as licence caps**
  during onboarding (see [03](./03-auth-onboarding-credentials.md))
- `status` enum(`inactive`,`active`,`suspended`,`cancelled`) default `inactive`
- `activated_at`, `suspended_at`, `suspension_reason`, `created_by`
- `apply_retro_void` (boolean; fillable in `School.php:29`) — quality-review propagation flag
- Relationships: `hasMany` schoolAdmins, teachers, students, grades, schoolSubjects, classes
  (`School.php:45-73`).

### `v2_branches` — campus under a school (`2026_06_18_000001…`)
Model `app/Models/V2/Branch.php`. Scope: school admin → own school's branches; branch admin →
own branch only (`Branch.php:19-30`).
- `school_id` FK → `v2_schools`
- `name`, `address` (nullable), `status` enum(`active`,`inactive`) default `active`
- Relationships: `belongsTo` School; `hasMany` classes (`branch_id`) (`Branch.php:32-40`).

### `v2_grades` — school-defined grade levels (`2026_06_13_000009…`)
Model `app/Models/V2/Grade.php`. Scope: `v2_school_admin` only (`Grade.php:30-42`), plus an
`ordered` scope (`orderBy sort_order, name`).
- `school_id` FK → `v2_schools`; `name`; `short_name`; `sort_order` int default 0;
  `is_active` bool default true
- **Unique** `(school_id, name)`
- Relationships: `belongsTo` School; `hasMany` schoolSubjects (`grade_id`), classes (`grade_id`).

### `v2_subjects` — **platform-wide** catalogue (`2026_06_13_000010…`)
Model `app/Models/V2/Subject.php`. **No global scope** — shared across all schools.
- `name`; `code` (nullable); `level` (nullable, e.g. O/A level); `is_active` bool default true
- No `school_id`. A subject becomes usable inside a school only via `v2_school_subjects`.

### `v2_school_subjects` — school×grade subject enablement (`2026_06_13_000011…`)
Model `app/Models/V2/SchoolSubject.php`. Scope: `v2_school_admin` only.
- `school_id` FK, `subject_id` FK, `grade_id` FK; `is_active` bool default true
- **Unique** `(school_id, subject_id, grade_id)`
- This is the M2M that answers "which subjects does school X offer for grade Y".

### `v2_classes` — a concrete class (`2026_06_13_000012…`, `branch_id` added `2026_06_18_000001`)
Model `app/Models/V2/SchoolClass.php` (**note: model name ≠ table name**). Scope: multi-guard
(`SchoolClass.php:34-48`).
- `school_id` FK; `branch_id` FK nullable (nullOnDelete); `grade_id` FK; `subject_id` FK
- `section` (string ≤10, nullable); `name`; `is_active` bool default true
- **Unique** `(school_id, grade_id, subject_id, section)` — a class is identified by
  grade + subject + section within a school
- Relationships: `belongsTo` school/branch/grade/subject; `hasMany` classTeachers, enrollments;
  `belongsToMany` teachers (via `v2_class_teachers`, withPivot `is_primary`) and students (via
  `v2_student_enrollments`, withPivot `status`) (`SchoolClass.php:80-92`).
- Helper `getFullNameAttribute()` builds `"{grade.short_name}-{section} {subject.name}"`
  (`SchoolClass.php:99-105`).

### `v2_class_teachers` — teacher↔class M2M (`2026_06_13_000013…`)
Model `app/Models/V2/ClassTeacher.php`. Scope: `v2_school_admin` only (`ClassTeacher.php:25-33`).
- `class_id` FK, `teacher_id` FK, `school_id` FK (denormalized for scoping); `is_primary` bool
  default true (cast to boolean)
- **Unique** `(class_id, teacher_id)`
- Written by `SchoolAdmin/ClassController::assignTeacher()` via `firstOrCreate`
  (`ClassController.php:87-110`); removed by `removeTeacher()` (`:112-127`). **Not** written by
  the teacher-creation flow.

### `v2_student_enrollments` — student↔class M2M (`2026_06_13_000014…`, `branch_id` added later)
Model `app/Models/V2/StudentEnrollment.php`. Scope: multi-guard (`StudentEnrollment.php:21-35`).
- `student_id` FK, `class_id` FK, `school_id` FK (denormalized); `branch_id` FK nullable;
  `status` enum(`active`,`inactive`,`transferred`) default `active`
- **Unique** `(student_id, class_id)`
- `scopeActive()` filters `status = 'active'` (`StudentEnrollment.php:57-60`).
- Written by `SchoolAdmin/StudentController::store()` (if `class_id` given) and `enroll()`
  (`firstOrCreate`); removed by `unenroll()` (`StudentController.php:55-64, 189-226`).

> **Ownership / tenancy on pivots.** `v2_class_teachers` and `v2_student_enrollments` carry a
> denormalized `school_id` so their global scopes and controller deletes can filter without an
> extra join. When inserting into a pivot, always set `school_id` to the acting admin's school
> (`$this->schoolId()`), as the controllers do.

## Core Flows

**Create a class (school admin).** `GET school/classes/create` → pick grade + subject
(from `Grade::active()` and `SchoolSubject::with('subject')`) → `POST school/classes`
(`ClassController::store`): validates `grade_id` belongs to the admin's school (403 otherwise),
sets `school_id` + `is_active`, creates the row, writes an audit log `class.created`
(`ClassController.php:30-51`).

**Assign a teacher to a class.** `POST school/classes/{class}/assign-teacher`
(`ClassController::assignTeacher`): authorizes the class, validates the teacher belongs to the
school, `ClassTeacher::firstOrCreate([...],['school_id'=>…,'is_primary'=>…])`, audit
`class.teacher_assigned` (`:87-110`).

**Enroll a student.** `POST school/students/{student}/enroll`
(`StudentController::enroll`): `StudentEnrollment::firstOrCreate` with `status='active'`, audit
`student.enrolled` (`StudentController.php:189-209`). A student's "primary grade" is derived from
active enrollments' classes (lowest `sort_order`), falling back to the denormalized
`v2_students.grade` column (`Student::primaryGrade()`, `Student.php:102-114`).

**Super-admin drill-down (read-only).** Every leaf is reached *through* a branch:
`schools/{school}/branches/{branch}/{grades|subjects|teachers|classes|students}/{id}`
(`routes/v2.php:99-107`). This enforces the hierarchy in the URL.

**Create a school / branch.** **Not implemented / future.** The super-admin
`schools/create` and `schools/{school}/edit` routes are **view stubs**, explicitly commented
"create not built yet" / "edit not built yet" (`routes/v2.php:93, 95`). Schools/branches are
currently seeded/created outside the UI (see [03](./03-auth-onboarding-credentials.md) and the
V2 seeders).

## Inputs

- Grade/subject/section selections; teacher & student IDs (route-bound, school-checked).
- CSV-style bulk payloads for teacher/student creation (see [03](./03-auth-onboarding-credentials.md)).
- The acting admin's `school_id` (from `BaseController::schoolId()`).

## Outputs

- Persisted org rows + pivots; audit-log entries (`AuditLogger::record(...)`).
- Tenant keys (`school_id`, `branch_id`) that downstream people/results rows inherit.

## Dependencies

- `app/Http/Controllers/V2/SchoolAdmin/BaseController.php` — anchors `school()` / `schoolId()`.
- `app/Services/V2/AuditLogger.php` — records every structural mutation.
- `HasHashid` trait on org models — obfuscated route IDs.
- Tenancy scopes from [01](./01-roles-permissions-tenancy.md).

## Security / Access Rules

- School-admin structural writes are guarded by `guest/auth:v2_school_admin` + the
  `authorize*()` 403 checks (`school_id` equality).
- Foreign keys use `nullOnDelete` for `branch_id`, so deleting a branch orphans (doesn't delete)
  its people/classes — they fall back to school-level scoping.
- Grades/subjects are only school-scoped for the school-admin guard; teacher/branch/student reads
  of those models are **not** auto-scoped (see [01](./01-roles-permissions-tenancy.md#core-flows)).

## Existing AI-Relevant Context

- `Subject.level` (O/A) + `Subject.code` map onto the Cambridge syllabus codes used by the
  question bank, letting AI features join a class's subject to real papers/questions.
- `SchoolClass::getFullNameAttribute()` gives a human label for prompts.

## AI Opportunities

- **Recommended for AI:** class/branch-level cohort analytics (roll up `v2_student_mistakes` by
  `class_id` via `v2_student_enrollments`) — the join keys already exist.
- **Recommended for AI:** suggest which `v2_school_subjects` a school should enable for a grade
  based on the classes it creates.

## AI Risks

- **Model/table name mismatch**: `SchoolClass` ↔ `v2_classes` and `StudentEnrollment` ↔
  `v2_student_enrollments`; a raw query on the wrong name fails silently or leaks.
- **Denormalized `school_id` on pivots** must be kept consistent; never insert a pivot row
  without it.
- The `branch_id`-nullable design means "no branch" is valid — AI code must handle null branches.

## Future Improvements (future)

- **Not implemented / future:** super-admin school/branch creation & edit UI (currently stubs).
- **Not implemented / future:** an audit *viewer* (`super-admin/audit` is a stub, `routes/v2.php:133`).
- **Partially implemented:** branch-aware analytics — data supports it, but scope coverage across
  models is uneven (see [01](./01-roles-permissions-tenancy.md#future-improvements-future)).
