# Student Progress Report Generation Feature

**Complete self-contained prompt for working on Reports in a separate session.**

**Sources:**
- Primary: `docs/modules/09-reports-pdf.md` (official module documentation - Module 09)
- Code review: `app/Services/V2/ReportService.php`, `app/Http/Controllers/V2/BranchAdmin/ReportController.php`, `app/Models/V2/Report.php`
- Views: `resources/views/v2/branch_admin/report/pdf.blade.php`, `resources/views/v2/branch_admin/analytics/student.blade.php`

**For the most up-to-date and complete documentation, always refer to `docs/modules/09-reports-pdf.md` first.** This prompt synthesizes and extends it for standalone use.

## Overview

The student progress report system allows **Branch Admins** to generate PDF progress reports for individual students across selectable time windows. Reports include:
1. Overall performance metrics (average score, tests completed, subjects)
2. Per-subject breakdown with topic-level accuracy bars
3. AI-generated or fallback narrative summary tailored to the student's performance
4. PDF download capability

All reports are stored in the database (`v2_reports` table) as JSON payloads, and PDFs are rendered on-demand from stored JSON (no pre-rendering).

---

## Architecture

### Database Schema

**Table: `v2_reports`**

```sql
CREATE TABLE v2_reports (
    id BIGINT PRIMARY KEY,
    school_id BIGINT NOT NULL (FK → v2_schools),
    branch_id BIGINT NULLABLE (FK → v2_branches),
    student_id BIGINT NOT NULL (FK → v2_students, CASCADE DELETE),
    generated_by BIGINT NULLABLE (FK to users; the branch admin who created it),

    period_key VARCHAR(20),         -- 'week', '2weeks', '3weeks', 'month', '2months', '3months', 
                                    -- 'quarter', '6months', '10months', 'all', 'custom'
    period_label VARCHAR(255),      -- Human-readable label: "the last week", "1 Jan – 5 Mar", etc.
    range_from TIMESTAMP NULLABLE,  -- Window start; NULL if period is 'all'
    range_to TIMESTAMP NULLABLE,    -- Window end; always set

    overall_avg TINYINT NULLABLE,   -- Denormalized: overall percentage for quick filtering/display
    tests_count SMALLINT,           -- Denormalized: how many tests student attempted in window
    source VARCHAR(20),             -- 'openai' (AI-generated), 'template' (deterministic), 'none' (empty)

    payload JSON,                   -- Full report content (see "Payload Structure" below)
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX(student_id, created_at)
);
```

### Payload Structure (JSON)

Each report's `payload` column stores:

```json
{
  "stats": {
    "overall": {
      "tests": 12,
      "avg": 68
    },
    "subjects": [
      {
        "subject": "Physics (O Level)",
        "tests_count": 5,
        "avg": 72,
        "topics": [
          {
            "topic": "Motion, forces and energy",
            "correct": 18,
            "total": 25,
            "percent": 72
          }
        ],
        "tests": [
          {
            "exam_id": 1,
            "title": "Mock Exam 1",
            "topic": "Motion, forces and energy",
            "score": 15,
            "total": 20,
            "percent": 75,
            "date": "2026-06-20T10:30:00Z"
          }
        ]
      }
    ]
  },
  "narrative": {
    "source": "openai|template|none",
    "summary": "Over the last week, Aisha completed 12 tests with an overall average of 68%...",
    "subjects": {
      "Physics (O Level)": "Aisha is averaging 72% in Physics...",
      "Chemistry (O Level)": "In Chemistry, ..."
    }
  }
}
```

### Model

**File: `app/Models/V2/Report.php`**

```php
namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasHashid;
    protected $table = 'v2_reports';
    protected $fillable = [
        'school_id', 'branch_id', 'student_id', 'generated_by',
        'period_key', 'period_label', 'range_from', 'range_to',
        'overall_avg', 'tests_count', 'source', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'range_from' => 'datetime',
            'range_to'   => 'datetime',
        ];
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function school(): BelongsTo { return $this->belongsTo(School::class, 'school_id'); }
}
```

---

## Services

### ReportService

**File: `app/Services/V2/ReportService.php`**

Builds a report's JSON payload (stats + narrative).

#### `build(Student $student, Carbon $since, string $periodLabel, ?Carbon $until = null): array`

Returns `['stats' => [...], 'narrative' => [...]]`.

**Flow:**
1. Call `ExamService::studentStats($student, $since, $until)` to get performance breakdown
2. Call `narrative()` to generate the summary (OpenAI or fallback)
3. Return both as associative array

#### `narrative(Student $student, array $stats, string $periodLabel): array`

Returns `['source' => 'openai|template|none', 'summary' => '...', 'subjects' => {...}]`.

**Decision logic:**
1. If no tests: `source='none'`, summary="No tests were completed..."
2. If `OPENAI_API_KEY` is set in config: try OpenAI
   - Sends compact stats + student first name to GPT
   - Returns JSON with `summary` (2–3 sentences, parent-friendly) and `subjects` (per-subject 1–2 sentence commentary)
   - On timeout/error: falls back to deterministic template
3. Otherwise: uses deterministic fallback template

#### OpenAI Prompt & Response

**To OpenAI:**
- **Model:** `config('services.openai.model', 'gpt-4o-mini')`
- **Response format:** `json_object`
- **Temperature:** 0.5 (balanced)
- **Timeout:** 30s
- **Content sent:** First name only + aggregated stats (no raw question data)

**Prompt structure:**
```
Student: {first_name}
Period: {period_label}
Overall average: {overall_avg}%
Subjects (with per-topic accuracy %):
{
  "subject": "Physics (O Level)",
  "average": 72,
  "tests": 5,
  "topics": [
    {"topic": "Motion, forces and energy", "percent": 72}
  ]
}

Write a concise progress report. Return STRICT JSON with keys:
- "summary": 2–3 sentences, parent-friendly, encouraging but honest, mention overall standing + biggest area to improve
- "subjects": object mapping each subject name to 1–2 sentence comment naming strongest/weakest topic with concrete next step

Use the student's first name.
```

#### Fallback (Deterministic) Narrative

Used when OpenAI is unavailable or fails.

**Summary:** Constructed from stats, using tiers:
- avg ≥ 75%: "performing strongly"
- avg ≥ 60%: "making solid progress"
- avg ≥ 40%: "developing but needs support"
- avg < 40%: "struggling and needs close attention"

**Per-subject:** Lists strongest/weakest topic with percent, includes actionable "recommend focused practice" language.

---

### ExamService

**File: `app/Services/V2/ExamService.php`**

#### `studentStats(Student $student, ?Carbon $since = null, ?Carbon $until = null): array`

Returns aggregated student performance over a window.

**Returns:**
```php
[
    'overall' => [
        'tests' => 12,
        'avg'   => 68,
    ],
    'subjects' => [
        [
            'subject'     => 'Physics (O Level)',
            'tests_count' => 5,
            'avg'         => 72,
            'topics'      => [
                [
                    'topic'   => 'Motion, forces and energy',
                    'correct' => 18,
                    'total'   => 25,
                    'percent' => 72,
                ]
            ],
            'tests'       => [
                [
                    'exam_id'  => 1,
                    'title'    => 'Mock Exam 1',
                    'topic'    => 'Motion, forces and energy',  // exam.topic?.title or 'Mixed'
                    'score'    => 15,
                    'total'    => 20,
                    'percent'  => 75,
                    'date'     => CarbonInstance,
                ]
            ],
        ]
    ]
]
```

**Key behavior:**
- Only counts `status='submitted'` exam attempts
- Date-filters by `$since` and `$until` (both applied to `submitted_at`)
- Groups by subject, then groups topics within each subject
- **Only includes topics that were actually tested in the window** (total > 0); unasked topics are omitted entirely (not shown as 0%)
- Untagged questions appear as topic `'Untagged'` if they were answered
- Orders topics by `external_id` (numeric ascending)

---

## UI & Routes

### Branch Admin View: `/v2/branch/student/{studentHashid}`

**File: `resources/views/v2/branch_admin/analytics/student.blade.php`**

Displays a student's stats with a modal to generate/download reports. **Part of the Stats module but hosts this module's controls.**

#### UI Flow

1. **Page loads:** Shows all saved reports for this student in a table (latest 12)
   - Columns: period label, overall avg% + test count, source (AI Assisted / Generated), date, PDF download link
2. **User clicks "Generate report" button:** Opens modal with duration presets + custom date range
3. **User selects period & clicks "Generate":** 
   - Modal shows AI loader animation (spinning icons + "Generating with AI...")
   - Form POSTs to `route('v2.branch.report.generate', $student)`
   - Controller builds report & saves to DB with denormalized summary columns
   - Page redirects to `v2.branch.student` with `success` flash + `report_ready` session key
4. **Page reloads:** New report appears at top of list
   - (Note: `report_ready` is flashed but not consumed by current blade - intended for auto-scroll in future)
5. **User clicks PDF:** GETs `route('v2.branch.report.pdf', $report)`, which renders A4 PDF on-the-fly from stored JSON

#### Modal Component

**Location:** `@push('modals')` stack in Blade (rendered as direct child of `<body>`, outside `.fade-in` wrapper, for true viewport centering via `position:fixed`).

**Duration presets offered:**
- 9 presets (each maps to a `[since, label, key]` triple in controller): 
  - `week` → 7 days → "the last week"
  - `2weeks` → 14 days → "the last 2 weeks"
  - `3weeks` → 21 days → "the last 3 weeks"
  - `month` → 31 days → "the last month" (default if unrecognized)
  - `2months` → 61 days → "the last 2 months"
  - `3months` → 92 days → "the last 3 months"
  - `quarter` → 120 days → "the last quarter"
  - `6months` → 183 days → "the last 6 months"
  - `10months` → 305 days → "the last 10 months"
- Custom: date range picker (from/to, validated server-side)

**Alpine.js state:**
- `open`: modal visibility
- `mode`: 'preset' or 'custom'
- `sel`: selected preset (week, month, etc.)
- `from` / `to`: custom date strings
- `loading`: shows AI loader overlay while submitting

---

## Controller

### ReportController extends BaseController

**File: `app/Http/Controllers/V2/BranchAdmin/ReportController.php`**

Base class: `app/Http/Controllers/V2/BranchAdmin/BaseController.php` provides helpers:
- `admin()` → `auth('v2_branch_admin')->user()`
- `branchId()` → `admin()->branch_id`
- `schoolId()` → `admin()->school_id`
- `branch()` → the branch relation

**Routes (inside `Route::middleware('auth:v2_branch_admin')` group):**
- `POST /v2/branch/analytics/students/{student}/reports` → `generate()` (route name: `v2.branch.report.generate`)
- `GET /v2/branch/analytics/reports/{report}/pdf` → `pdf()` (route name: `v2.branch.report.pdf`)

Both routes use **hashid route keys** (`HasHashid` trait) so `{student}` and `{report}` bind by reversible hashid, with fallback to raw integer id.

#### Multi-tenancy & Authorization

**Branch isolation enforced two ways:**

1. **Global scope on Student model** (`app/Models/V2/Student.php:49–65`): When guard is `v2_branch_admin`, route-model binding automatically appends `where('branch_id', <admin branch_id>)`. A student outside the admin's branch returns 404 (not found), not 403 (forbidden).

2. **Explicit abort_unless checks:**
   - `generate()` line 26: `abort_unless($student->branch_id === $this->branchId(), 403)`
   - `pdf()` line 55: `abort_unless($report->branch_id === $this->branchId(), 403)`

Note: `Report` model has **no** global scope, so the explicit check on `pdf()` is the *only* tenancy guard there (load-bearing).

#### `public generate(Student $student, Request $request, ReportService $reports)` (lines 24–60)

**Authorization:** `abort_unless($student->branch_id === $this->branchId(), 403);`

**Request input:**
```
duration = 'week' | '2weeks' | '3weeks' | 'month' | '2months' | '3months' | 'quarter' | '6months' | '10months' | 'custom' | 'all'
from = (if custom) date string
to = (if custom) date string
```

**Flow:**
1. Call `window($request)` to resolve duration → `[since, until, label, key]`
2. Call `$reports->build($student, $since, $label, $until)` → `['stats' => [...], 'narrative' => [...]]`
3. Extract `overall_avg` and `tests_count` from stats
4. `Report::create([...])` with payload
5. If tests_count === 0: redirect with `info` flash "No completed tests for {$student->name} in {$label} - an empty report was saved."
6. Otherwise: redirect with `success` flash "{$tests} tests, {$avg}% average."
7. Both redirects include `report_ready` session key (report ID) for highlighting animation

#### `private window(Request $request): array` (lines 73–114)

Pure mapper, returns `[Carbon $since, string $label, string $key]`.

**Preset logic:**
```php
match ($duration) {
    'week'     => [7,   'the last week'],
    '2weeks'   => [14,  'the last 2 weeks'],
    '3weeks'   => [21,  'the last 3 weeks'],
    'month'    => [31,  'the last month'],      // default (any unrecognised value)
    '2months'  => [61,  'the last 2 months'],
    '3months'  => [92,  'the last 3 months'],
    'quarter'  => [120, 'the last quarter'],
    '6months'  => [183, 'the last 6 months'],
    '10months' => [305, 'the last 10 months'],
    'all'      => [Carbon::createFromTimestamp(0), 'all time'],  // epoch
    'custom'   => [validate from/to]
}
```

- **Presets:** `$since = now()->subDays($days)->startOfDay()`, `$until = now()`
- **All:** `$since = Carbon::createFromTimestamp(0)` (epoch), `$until = now()`, but `range_from` stored in DB as `NULL` to signal all-time
- **Custom:** validates `from` and `to` are dates before today, `from <= to`, returns label as "j M Y – j M Y" format
- **No validation error handling:** unrecognised duration values silently fall through to monthly (match default)

#### `pdf(Report $report)`

**Authorization:** `abort_unless($report->branch_id === $this->branchId(), 403);`

**Flow:**
1. Load report relationships (student, branch, school)
2. Use `Barryvdh\DomPDF\Facade\Pdf::loadView()` to render `v2.branch_admin.report.pdf`
3. Pass: `student`, `branch`, `school`, `period`, `stats`, `narrative`, `generatedAt`
4. Return PDF download with filename `report-{slug}-{date}.pdf`

---

## PDF View

**File: `resources/views/v2/branch_admin/report/pdf.blade.php`**

Renders the full PDF from stored report JSON.

### Sections

1. **Header band:** Dark blue (navy) band with school/branch/student name + period
2. **Overview card:** 3-column table (Overall average%, Tests completed, Subjects count)
3. **Summary card:** Boxed narrative summary from `$narrative['summary']`
4. **Per-subject cards:** For each subject:
   - Subject name + overall subject average % (color-coded badge: green ≥60%, orange ≥40%, red <40%)
   - Topic breakdown table: topic name, bar chart (%, color-coded), score fraction (correct/total)
   - Subject-specific narrative comment (if available in `$narrative['subjects']`)
5. **Footer:** Generated timestamp, "AI-assisted performance report", TopicalEd link + logo

### Colors

- Green (#5FA052): ≥ 60%
- Orange (#D9952B): ≥ 40% and < 60%
- Red (#C64C44): < 40%
- Navy (#061C30): header background

### PDF Dependencies

- **Library:** `barryvdh/laravel-dompdf` (already in composer.json)
- **Font:** DejaVu Sans (bundled with DomPDF, supports Unicode)
- **Logo:** `public/images/topicaled-logo.svg` (checked for existence; omitted if missing)

---

## Implementation Checklist

### Database
- [x] Migration creates `v2_reports` table with full schema
- [x] Indexes on (student_id, created_at)

### Models
- [x] `Report` model with `HasHashid`, casts, relationships

### Services
- [x] `ReportService::build()` - orchestrates stats + narrative
- [x] `ReportService::narrative()- OpenAI with fallback logic
- [x] `ReportService::openai()` - calls GPT-4o-mini, error handling
- [x] `ReportService::fallback()` - deterministic narrative
- [x] `ExamService::studentStats()` - aggregates performance by subject/topic

### Controller
- [x] `ReportController::generate()` - window resolution + DB save + redirects
- [x] `ReportController::window()` - preset/custom date logic
- [x] `ReportController::pdf()` - DomPDF render + download

### Views
- [x] `resources/views/v2/branch_admin/analytics/student.blade.php` - modal + report list
- [x] `resources/views/v2/branch_admin/report/pdf.blade.php` - PDF template

### Routes
- [x] `POST /v2/branch/student/{student}/report` → `ReportController@generate`
- [x] `GET /v2/branch/report/{report}/pdf` → `ReportController@pdf`

---

## Configuration & Environment

### OpenAI (.env / config/services.php)

```php
// .env
OPENAI_API_KEY=sk-...          // optional; if unset, all narratives use fallback template
OPENAI_MODEL=gpt-4o-mini       // optional; defaults to gpt-4o-mini

// config/services.php:31–34
'openai' => [
    'key'   => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],
```

**Hard-coded (not configurable via env):**
- OpenAI timeout: `30` seconds
- Temperature: `0.5` (balanced)
- Response format: `json_object`

### DomPDF

- **Package:** `barryvdh/laravel-dompdf ^3.1` (in `composer.json:13`)
- **Config:** **No `config/dompdf.php` is published** - defaults apply (remote image fetching off, default paper, etc.)
- **Paper size:** Set per-call via `->setPaper('a4')` in `pdf()` (not configurable via env)
- **Font:** Hard-coded to `DejaVu Sans` (only font dompdf ships with full glyph support)

### Miscellaneous

| Setting | Where | Purpose |
|---|---|---|
| `public/images/topicaled-logo.svg` | Referenced in `pdf.blade.php:98–101` | Optional; embedded in PDF footer if exists, omitted if missing. Loaded via `public_path()` (filesystem), not URL (dompdf reads from disk). |
| `APP_KEY` | Framework config | Indirectly: `HasHashid` keys the `{report}`/`{student}` route hashids off `APP_KEY`. Rotating it changes the hashids (raw-id binding still works). |

---

## Testing & Examples

### Generate a report (via tinker or test)

```php
$student = Student::find(1);
$since = Carbon::now()->subDays(7);
$until = Carbon::now();

$service = app(ReportService::class);
$data = $service->build($student, $since, 'the last week', $until);

$report = Report::create([
    'school_id'    => $student->school_id,
    'branch_id'    => $student->branch_id,
    'student_id'   => $student->id,
    'generated_by' => auth('v2_branch_admin')->id(),
    'period_key'   => 'week',
    'period_label' => 'the last week',
    'range_from'   => $since,
    'range_to'     => $until,
    'overall_avg'  => $data['stats']['overall']['avg'],
    'tests_count'  => $data['stats']['overall']['tests'],
    'source'       => $data['narrative']['source'],
    'payload'      => $data,
]);

// View the PDF:
// GET /v2/branch/report/{report->getHashId()}/pdf
```

### No tests in window

If a student has no submitted attempts in the chosen window:
- Report still saves with `tests_count=0`, `overall_avg=null`
- Source is `'none'`, narrative summary is canned
- PDF displays "No tests were completed in this period"
- Flash message says "...an empty report was saved."

### OpenAI timeout / failure

If OpenAI fails within the 30s timeout:
- ReportService logs a warning
- Falls back to deterministic narrative
- Report source is `'template'`, not `'openai'`
- User sees no error; PDF renders normally with the fallback narrative

---

## Common Customization Points

### Change the preset periods

Edit the array in `ReportController::window()` - add/remove durations and adjust labels.

### Customize the fallback narrative

Edit `ReportService::fallback()` - change the standing tiers, template strings, or recommendation logic.

### Customize the OpenAI prompt

Edit the `$prompt` string in `ReportService::openai()` - adjust model, temperature, or system message.

### PDF styling

Edit colors, spacing, fonts in `resources/views/v2/branch_admin/report/pdf.blade.php` `<style>` block.

### Denormalized fields in v2_reports

If you want to track additional summary metrics (e.g., strongest/weakest topic), add columns to the migration and populate them in `ReportController::generate()` before the `create()` call. This improves query performance for report listings without re-parsing JSON every time.

---

## Known Limitations, Edge Cases & TODOs

1. **Topics never tested:** If a student never answers questions from topic X in the window, topic X doesn't appear in the report (intentional design - cleaner, avoids 0% rows). To change: remove `.filter(fn ($r) => (int) $r->total > 0)` in `ExamService::studentStats()`.

2. **No regenerate / delete / dedicated-list actions:** Only `generate` + `pdf` exist. Listing is a side-effect of the analytics `student` page (latest 12). Old reports accumulate with no pruning. *(Storage grows unbounded.)*

3. **No automated tests:** No feature/unit tests for `ReportService` or the controller.

4. **`source = openai` can be misleading:** Set whenever the HTTP call returns 2xx, even if the model returned malformed JSON and fallback text was substituted. The list shows "AI Assisted" for effectively template-text rows in that edge case.

5. **No input validation on `duration`:** Unrecognised values silently degrade to monthly via the match default. No error is returned; the form accepts any string.

6. **`generated_by` has no FK constraint:** A dangling branch-admin id would persist (column is nullable, only used for provenance). Low impact but loose.

7. **`Report` has no global scope:** The `pdf()` `abort_unless` is the only tenancy guard; a future refactor that drops that line silently leaks cross-branch access. Document before touching.

8. **`report_ready` flash is dead:** `generate()` flashes `report_ready` with the new report id, but `student.blade.php` never reads it (no auto-scroll). Harmless but indicates incomplete UX.

9. **PDF ignores `stats.subjects[].tests[]`:** The per-test breakdown stored in payload is never shown in the PDF (only on web). By design (aggregate reports only), but maintainers might expect it.

10. **No remote images in PDF:** Only local `public_path()` logo is embedded; secure-image crops are not rendered (dompdf remote-fetch is off by default). Reports are aggregate-only, so no question images needed.

11. **Synchronous OpenAI call:** OpenAI request is synchronous within the request (30s timeout); a slow/timing-out API extends the generate request latency before falling back. No queueing or async.

12. **OpenAI is optional:** Without `OPENAI_API_KEY` every report is `source = template`. No UI signal that AI is unavailable beyond the "Generated" vs "AI Assisted" label.

---

## File Manifest

| File | Purpose |
|------|---------|
| `app/Services/V2/ReportService.php` | Build report payload (stats + narrative) |
| `app/Services/V2/ExamService.php` | Aggregate student performance metrics |
| `app/Http/Controllers/V2/BranchAdmin/ReportController.php` | Generate + download reports |
| `app/Models/V2/Report.php` | Database model + relationships |
| `database/migrations/2026_06_18_000002_create_v2_reports_table.php` | Schema |
| `resources/views/v2/branch_admin/analytics/student.blade.php` | Modal + report list |
| `resources/views/v2/branch_admin/report/pdf.blade.php` | PDF template |
| `.env` / `config/services.php` | OpenAI credentials (optional) |

---

## References

- **Laravel Facades & Services:** https://laravel.com/docs/services
- **DomPDF:** https://github.com/barryvdh/laravel-dompdf
- **OpenAI Chat Completions:** https://platform.openai.com/docs/api-reference/chat/create
- **Blade Components & Stacks:** https://laravel.com/docs/blade#stacks
- **Alpine.js:** https://alpinejs.dev/

