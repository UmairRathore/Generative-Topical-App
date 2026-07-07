# 01 — Existing AI Usage (the House Precedent)

> Forward-looking AI design doc. Grounded in current code; features described here are NOT yet built unless labelled **Implemented**.

**Cross-links:** [02 — AI Integration Opportunities](./02-ai-integration-opportunities.md) ·
[03 — AI Context Contracts](./03-ai-context-contracts.md) ·
[04 — AI Safety Rules](./04-ai-safety-rules.md) ·
[05 — AI Roadmap](./05-ai-roadmap.md) ·
Modules: [../modules/12-mistake-bank.md](../modules/12-mistake-bank.md) ·
[../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md) ·
`docs/modules/09-reports-pdf.md` (top-level module docs) ·
`docs/modules/08-stats-analytics.md`

---

## TL;DR

There is exactly **one** live AI call in the entire application today. It generates the
**branch-admin student progress-report narrative** from a student's real stats. Everything else
labelled "AI" in this codebase (learning assets, worked solutions, widgets) is generated
**outside the app** by an offline Claude Code / workflow pass and only *displayed* — see
[../modules/13-learning-hub-worked-solutions-assets.md](../modules/13-learning-hub-worked-solutions-assets.md).

Status of the one live call: **Partially implemented** — it works end-to-end but is a single,
narrow feature with a deterministic fallback and no usage/cost logging.

**This file is the canonical precedent.** Every future AI feature (tutor, notes AI, new reports,
exam generator) must reproduce the four properties this one already has:
minimal PII, hard grounding, guaranteed fallback, and provider indirection through config.

---

## Where it lives

| Concern | Location |
|---|---|
| The AI call itself | `app/Services/V2/ReportService.php` — `openai()` (lines 53–104) |
| Fallback (no key / on error) | `ReportService::fallback()` (lines 108–151) |
| Entry point that decides AI vs fallback | `ReportService::narrative()` (lines 33–49) |
| Stats it summarises | `ReportService::build()` → `ExamService::studentStats()` (`app/Services/V2/ExamService.php:433`) |
| Invoked by | `app/Http/Controllers/V2/BranchAdmin/ReportController.php` — `generate()` (lines 24–60) |
| Provider config | `config/services.php` — `openai.key`, `openai.model` (lines 31–34) |
| Result stored in | `v2_reports.payload` (JSON) + denormalised `v2_reports.source` column (migration `database/migrations/2026_06_18_000002_create_v2_reports_table.php`) |
| Rendered as | PDF from the stored JSON, on demand — `ReportController::pdf()` (lines 63–80); nothing is cached |

The acting role is **Branch Admin** (guard `v2_branch_admin`); the controller extends the
branch-scoped `BaseController` and asserts `$student->branch_id === $this->branchId()` before doing
anything (`ReportController.php:26`).

---

## Exactly what the call does

Verified against `app/Services/V2/ReportService.php`:

- **Provider gate.** `narrative()` reads `config('services.openai.key')`. If it is set, it tries
  `openai()`; **any** `\Throwable` is caught, logged (`Log::warning('OpenAI report generation
  failed, using fallback: …')`), and it falls through to `fallback()` (lines 39–48). If no key is
  set it never calls out at all.
- **Empty-window short-circuit.** If `stats['overall']['tests'] === 0`, it returns a fixed "no
  tests were completed" narrative and never calls the model (lines 35–37).
- **Endpoint / transport.** `Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/chat/completions', …)`
  (line 84). Laravel's HTTP client; a 30-second timeout.
- **Model.** `config('services.openai.model', 'gpt-4o-mini')` (line 85) — i.e. env `OPENAI_MODEL`,
  defaulting to `gpt-4o-mini`.
- **Messages.** A **system** message pinning the persona — *"You are a concise, encouraging
  academic mentor writing short progress reports for a Cambridge O/A-Level school. Plain,
  parent-friendly language. Always return valid JSON."* — plus a **user** message carrying the
  compacted stats + instructions (lines 86–89).
- **Structured output.** `'response_format' => ['type' => 'json_object']` (line 90) forces a JSON
  object back. `'temperature' => 0.5` (line 91).
- **Response handling.** `$resp->throw()` (surfaces HTTP errors into the catch), then
  `json_decode($resp->json('choices.0.message.content'))`. Missing keys fall back to the
  deterministic template's values field-by-field (`$json['summary'] ?? $fallback['summary']`,
  lines 95–103). The returned array is tagged `'source' => 'openai'`.

### The payload it sends (minimal-PII precedent)

Built in `openai()` (lines 55–82). It sends **only**:

- The student's **first name only** — `strtok($student->name, ' ')` (line 55). No surname, no
  email, no roll number, no id, no school/branch name.
- Numbers: overall average %, and per subject → per topic → per subtopic **accuracy percentages**
  plus test counts (lines 56–70).
- Per subject, the subject's **full real syllabus topic list** (`syllabus_topics`, sourced from
  `ExamService::studentStats()`'s `all_topics`, which is every `v2_topics.title` for the subject —
  see `ExamService.php:485–521`).

That is the whole payload. No raw question text, no other students, no PII beyond a first name.
**Future AI features should default to this same minimal-PII posture** (see
[04 — AI Safety Rules](./04-ai-safety-rules.md), rule 11).

### The grounding rule (the most important precedent)

The user prompt (lines 72–82) instructs the model, for each subject, to:

1. Name the strongest topic.
2. Name the weakest topic and, using per-subtopic accuracy, name the 1–3 specific subtopics to
   focus on first.
3. Recommend 1–2 prerequisite/foundational topics to revisit —

> *"chosen ONLY from that subject's `syllabus_topics` list, and never invent a topic that is not in
> that list."*

This is a **hard grounding constraint**: the model may only recommend topics that actually exist in
the subject's real syllabus. It cannot hallucinate a topic. The `syllabus_topics` list is the
allowed vocabulary; the numbers are the evidence.

Every future generator inherits this pattern:
- Reports must ground conclusions in real stats (never invent marks/trends).
- The tutor must ground explanations in the question's approved learning assets and its real
  correct answer (never free-generate physics).
- The exam generator must draw only from `v2_questions` and obey teacher-selected topics/counts.

See [04 — AI Safety Rules](./04-ai-safety-rules.md), rules 1, 4, 6, 7.

### The fallback (guaranteed availability precedent)

`fallback()` (lines 108–151) produces the *same shape* — `{ source: 'template', summary,
subjects }` — deterministically from the stats: a banding of the overall average into a standing
phrase, then per subject the strongest topic, the weakest topic, and (drilling into that weak
topic's subtopics) the 1–2 lowest-accuracy subtopics by real data. It never calls out.

Consequence: **the PDF always renders**, key or no key, model up or down. The `source` field
(`openai` / `template` / `none`) records which path produced it, and is persisted to
`v2_reports.source` for auditing which reports were AI-written.

**Every future AI feature should ship its fallback first** and treat the model as an enhancement,
not a dependency (see [05 — AI Roadmap](./05-ai-roadmap.md), and rule 5 in
[04](./04-ai-safety-rules.md)).

---

## Provider stance (decided)

- **Existing usage is OpenAI** and is documented here factually. It works; do **not** rip it out.
- **New AI (tutor, notes AI, new reports, exam generator) should standardise on Claude / Anthropic**
  — the house default — reusing the *patterns* proven here (config-driven provider indirection,
  minimal PII, hard grounding, guaranteed fallback, `response_format`-style structured output).
- The single `ReportService` OpenAI call is the *one* place that may optionally be migrated to
  Claude later, once a shared AI client abstraction exists. Until then it stays as-is.

**Recommendation for the shared client:** introduce a provider-agnostic
`App\Services\AI\*` seam (e.g. an `AiClient` interface with `chatJson(system, user, opts)`), with a
Claude implementation as the default and the existing OpenAI call adaptable behind it. Config keys
should mirror the existing `services.openai` block — e.g. add `services.anthropic.key` /
`services.anthropic.model` to `config/services.php`. See the API reference (`/claude-api` skill)
for current Claude model ids, `messages` API shape, structured output, and token/cost fields before
implementing.

---

## Gaps / flags

- **No AI-usage / cost / audit logging table exists yet.** (**Recommended for AI**.) The only trace
  today is `v2_reports.source` (`openai` vs `template`) and an ad-hoc `Log::warning` on failure. No
  request/response tokens, latency, model, or cost is recorded anywhere. There is a general audit
  table (`v2_audit_logs`, migration `2026_06_13_000006`) and an `App\Services\V2\AuditLogger`
  service, but AI calls do not use it. A dedicated `ai_interactions` table is proposed in
  [04 — AI Safety Rules](./04-ai-safety-rules.md) (rule 10) following the `v2_audit_logs` pattern —
  this should be built **before or alongside** the first new AI feature.
- **Single feature only.** No AI is wired into exams, the Mistake Bank, Notes, the student/teacher
  dashboards, or any other report scope. Those are all opportunities, mapped in
  [02 — AI Integration Opportunities](./02-ai-integration-opportunities.md).
- **No retry / rate-limit handling** beyond the blanket catch-and-fallback. Acceptable for a
  low-volume report button; **not** acceptable for an interactive tutor (see
  [05 — AI Roadmap](./05-ai-roadmap.md), Phase 1 risks).
