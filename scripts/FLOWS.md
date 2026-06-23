# Module flow captures — authoring guide

`scripts/capture-module-flows.mjs` reads every `docs/modules/NN-*.md`, finds the
` ```flow ` block(s) inside, and drives the real V2 app with Playwright — logging in
as the right role, clicking through your steps, and screenshotting each labelled
screen. Output lands in `screenshots/flows/` (one folder per module/flow) with an
`index.html` gallery and `report.md`.

You maintain the flows **inside the module docs** — add or edit a ` ```flow ` block,
re-run, and the new screenshots regenerate. No code changes needed.

## Run it

```bash
# 1. dev server must be up (separate terminal), V2 demo data seeded
php8.4 artisan serve            # http://127.0.0.1:8000

# 2. capture
npm run capture:flows                       # every module that has a flow block
node scripts/capture-module-flows.mjs --module=07     # just module 07
node scripts/capture-module-flows.mjs --headed        # watch the browser drive it
node scripts/capture-module-flows.mjs --base=http://127.0.0.1:8001
```

Open `screenshots/flows/index.html` to view the result.

## The flow block

Put one (or more) fenced ` ```flow ` blocks anywhere in a module doc. It's JSON —
`//` comments and trailing commas are allowed (stripped before parsing).

````md
```flow
{
  "name": "Teacher creates & releases an exam; student takes it; results released",
  "steps": [
    { "as": "teacher" },
    { "goto": "/v2/teacher/exams", "shot": "teacher-exams", "caption": "Teacher's exams list" },
    { "click": "Create Exam", "shot": "random-form", "caption": "Random generator" },
    { "fill": { "name": "title", "value": "Mock {{run}}" } },
    { "select": { "name": "class_id", "label": "AS-A Physics" } },
    { "fill": { "name": "question_count", "value": "5" } },
    { "click": "Generate Test", "waitFor": "networkidle" }
  ]
}
```
````

## Steps & actions

A step is an object. Keys run in this order within one step:
`viewport → as → goto → fill → select → check → msselect → answerAll → capture → clickSelector → click → waitFor → shot`.
So `{ "click": "Save", "shot": "saved" }` clicks, then screenshots.

| Key | Value | What it does |
|---|---|---|
| `as` | `"teacher"` etc. | Start a **fresh session** and log in as this role. Roles: `super_admin`, `school_admin`, `branch_admin`, `teacher`, `student`. |
| `goto` | `"/v2/teacher/exams"` | Navigate to a path (or full URL). Supports `{{vars}}`. |
| `fill` | `{ "name": "title", "value": "x" }` | Fill an input by `name` (or `selector`). |
| `select` | `{ "name": "class_id", "label": "AS-A Physics" }` | Native `<select>` — pick by `label`, `value`, or `index`. |
| `check` | `{ "name": "release_results" }` | Tick a checkbox/radio (force). |
| `msselect` | `{ "text": "Forces" }` | The custom Alpine multi-select (`.ms-control` + search + `.ms-opt`), e.g. teacher topic picker. |
| `answerAll` | `"first"` or `"last"` | On the student take page: pick that option for **every** question. |
| `capture` | `{ "capture": "examHash", "fromUrl": "exams/(?!create\|custom)([A-Za-z0-9]+)" }` | Regex group 1 of the current URL → a `{{var}}`. |
| `click` | `"Generate Test"` | Click by visible button/link text. A value starting with `.`/`#`/`[` is treated as a CSS selector. |
| `clickSelector` | `"button.btn-primary"` | Click by explicit CSS. |
| `waitFor` | `"networkidle"`, a number (ms), or a selector | Wait before continuing. |
| `viewport` | `"mobile"` or `"desktop"` | Switch viewport (desktop 1440×1200, mobile 390×844). |
| `shot` | `"slug"` + `caption` | Full-page screenshot, captioned in the gallery. |

## Variables & templating

- `{{run}}` — a short per-run id; use it for unique titles so each run is traceable.
- `{{anything}}` — set by a `capture` step earlier in the same flow.

Because the model's hashids are global (keyed by `APP_KEY`), an exam hashid captured
from the teacher URL (`/v2/teacher/exams/<hash>`) is the **same** param the student
routes accept (`/v2/student/exams/<hash>/take`). That's how a strict end-to-end flow
drives one exam across both actors without matching list rows by hand.

## Demo accounts (override via env)

| Role | Default email | Env override |
|---|---|---|
| teacher | `bilal@thesage.edu.pk` (owns class *AS-A Physics*) | `TEACHER_EMAIL` / `TEACHER_PASSWORD` |
| student | `2026-001@thesage.edu.pk` (enrolled in *AS-A Physics*) | `STUDENT_EMAIL` / `STUDENT_PASSWORD` |
| branch_admin | `gulberg@thesage.edu.pk` | `BRANCH_ADMIN_EMAIL` / … |
| school_admin | `admin@thesage.edu.pk` | `SCHOOL_ADMIN_EMAIL` / … |
| super_admin | `super@topicaled.com` | `SUPER_ADMIN_EMAIL` / … |

All default passwords are `password`. The teacher↔student pair above is consistent
(same class), which is what makes the strict E2E exam flow work end to end.

## Robustness

- Each step is wrapped in try/catch — a failing selector logs a warning, saves an
  `ERROR-*.png` of the failure state (bordered red in the gallery), and the flow
  continues. So one stale selector never kills the whole capture.
- Each actor switch (`as`) opens a clean browser context — no guard cross-talk.
- The submit `confirm()` dialog on the student take page is auto-accepted.
