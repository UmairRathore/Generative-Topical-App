# Student AI Tutor V1 — Implementation Record (SHIPPED)

> Status: **complete and verified end-to-end** on branch `feat/ai-tutor` (July 2026).
> This document is the handoff/context prompt: what exists, how the flow works,
> the stack, and every migration. A future session should read this before
> touching the tutor.

---

## 1. What was built

A student-facing, **Socratic, grounded AI tutor** inside the Learning Studio
(the React page behind every Mistake Bank item), plus AI-generated **mini
quizzes** scored separately from official exams, with **deep usage logging**.

Two chat modes per mistake (both authorized through the owned, released
`StudentMistake`):
- **My mistake** — teaches against the student's own wrong answer (includes
  `student_answer` + `behaviour` history in the AI context).
- **This question** — about the question itself (personal attempt data omitted).

Schema supports `subtopic | topic | subject` chats for later; V1 activates
only `question | mistake`.

Explicitly NOT built (out of V1 scope): teacher/admin AI reports, AI exam
generator, Notes AI, voice tutor, embeddings/RAG (skeleton dirs only),
streaming responses.

---

## 2. Architecture & request flow

**Monorepo, three layers. Laravel is the ONLY gatekeeper and source of truth.**

```
React (Inertia page, chat UI)
   │  axios → Laravel JSON endpoints (session auth, CSRF)
   ▼
Laravel (auth · tenancy · release gates · ownership · asset visibility ·
         context assembly · ALL persistence · logging)
   │  HTTP POST + X-Internal-Token (shared secret)
   ▼
python-ai (FastAPI, STATELESS, NO database access - prompt formatting +
           provider call only)
   │  official OpenAI SDK
   ▼
OpenAI (model from env: OPENAI_MODEL=gpt-4o-mini)
```

React never calls Python. Python never touches the DB. Python must never
become a second student backend.

### Chat message lifecycle
1. Student opens studio `?tab=tutor` → panel POSTs `tutor.open`
   (`{source_type}`) → Laravel authorizes (ownership 403, release gate 404,
   school scope via model global scope), `firstOrCreate`s the chat (one active
   chat per student+mistake+source_type), returns chat + full message history
   + ALL quizzes + per-chat URLs.
2. Student sends a message → POST `tutor.chats.message` → Laravel re-checks
   every guard → persists the user message → `StudentTutorContextBuilder`
   assembles the grounded context (below) → `TopicalAiClient` POSTs
   `/tutor/chat` to python-ai with `{chat_ref, context, history (last 10
   turns, 1500 chars each), message}`.
3. python-ai builds the system prompt (Socratic persona + hard rules + level
   header + context JSON), calls OpenAI chat.completions (max_tokens 700,
   plain markdown — NO json response_format for chat), returns
   `{ok, answer, meta{provider, model, input/output/total_tokens, cost_usd,
   latency_ms}}`.
4. Laravel persists the assistant message (+model/tokens), bulk-inserts
   `v2_ai_tutor_context_items` (which assets grounded this reply), stamps
   `last_message_at`, writes one `v2_ai_interaction_logs` row, returns the
   message to React.
5. Failure path: user message is kept (retry-friendly), log row
   `status=error` with latency + error text, HTTP 502 `{ok:false}` → UI shows
   error bubble + Retry.

### Context payload (assembled ONLY from data the student can already see)
- `student.first_name` ONLY (strtok) — no surname/email/ids (PII rule).
- `grade_class.grade` (Student::primaryGrade()).
- `curriculum`: subject name + **level** (`v2_subjects.level`, e.g. "O Level")
  + **syllabus_code** (e.g. 5054) + topic + subtopic. The level is stated at
  the TOP of the system prompt as a hard difficulty ceiling (O Level students
  must never get A Level content — enforced after a real drift incident).
- `question`: stem, options, **correct_answer** (question.correct_answer
  falling back to mistake.correct_option; if both null → 422, chat won't
  open ungrounded), difficulty, source_paper, `diagram_references` as
  `{question_id, image_path, caption}` — NEVER signed URLs or bytes.
- mistake mode only: `student_answer` + `behaviour` (mistake_count, mastery
  status, review_count, first/last_wrong_at).
- `grounding_assets` via `MistakeBankService::visibleAsset()` (approved/edited
  ONLY): worked_solution, option_explanations, flashcards, memcards, mermaid,
  revision_notes, common_mistakes + interactive_widget (**type only** — the
  JS config is stripped, it was pure token waste).
- `recent_context` (score trend + 5 weakest topics in the subject) — FIRST
  TURN ONLY (keeps later prompts cheap + stable for provider prompt caching).

### Cost controls (measured live: chat ≈ $0.0004, quiz ≈ $0.0005 per call)
- Asset content caps: worked_solution 6000 chars, option_explanation 4000,
  mermaid 1500, revision/common 2500, card decks sliced to 6 items.
- History: 10 turns, 1500 chars/turn. Output caps: chat 700 tokens, quiz 2000.
- Prompt instructs replies under ~180 words, one guiding question per turn.

### Mini quiz flow
- "Quiz me" → POST `tutor.chats.quiz` → same context (stats excluded) →
  python-ai asks for STRICT JSON (`response_format json_object` — quiz only),
  pydantic-validates (3–5 questions, 2–5 options, unique labels,
  correct_option ∈ labels, model self-check that the key matches its own
  explanation, stems stripped of model numbering), one repair retry, else 502.
  Laravel re-validates (belt & braces), stores quiz + questions in a
  transaction, logs `feature=tutor_quiz`.
- Client JSON WITHHOLDS correct_option/explanation until the attempt.
- Quiz is a **checkpoint**: composer locks ("finish to continue"), quiz can't
  be dismissed unattempted, survives refresh (chat payload carries all
  quizzes + attempted results). Submit → POST `tutor.quizzes.attempt` → pure
  PHP scoring (NO AI call), attempt row saved, quiz → `attempted`, second
  attempt → 409. **Never touches v2_exam_* tables** (test-proven).
- After attempt the quiz stays in the thread as a collapsed score bar
  (expandable review with verdicts + explanations).

---

## 3. Stack (frameworks / languages / libraries)

| Layer | Tech |
|---|---|
| Backend app | **Laravel 12** (PHP ^8.2; dev runs `php8.4` — system `php` 8.5 lacks pdo_mysql), MySQL (prod/dev), sqlite `:memory:` for tests |
| AI service | **Python 3.12**, **FastAPI** (+ uvicorn), **pydantic v2** + pydantic-settings, **openai** official SDK (≥1.40) |
| Frontend | **React 18.3** via **Inertia.js** (the "Learning Studio" bundle `resources/js/learn/`), **Vite** (dev server needs `@viteReactRefresh` in blades — was missing app-wide, fixed), axios (CSRF via meta tag) |
| Styling | Hand-rolled CSS (`learn.css` ls-* tokens, `tutor.css` tut-*), Notes-style themed scrollbars; NO Tailwind in this bundle |
| Existing house AI precedent | `ReportService` → OpenAI via Laravel Http facade (untouched) |
| Provider default | `OPENAI_MODEL=gpt-4o-mini` — env-only, never hardcoded; swap model by env change. Anthropic provider = stub behind `providers/base.py` seam for later |

Dev runner: **`./dev.sh`** at repo root (bash) — starts python-ai (uvicorn
:9001), Laravel (`php8.4 artisan serve` :8000) and Vite (:5173) with prefixed
logs, port checks, auto-venv-bootstrap, nvm sourcing; Ctrl+C stops all.
Config: Laravel `services.python_ai` = `{url (default http://127.0.0.1:9001),
token (PYTHON_AI_TOKEN), timeout 90}`; python-ai `.env` = `OPENAI_API_KEY,
OPENAI_MODEL, INTERNAL_TOKEN (must equal PYTHON_AI_TOKEN), PORT=9001`.

---

## 4. Migrations (7 new, all `database/migrations/2026_07_07_0000NN_*`)

All tables are V2-prefixed; question bank stays immutable (`question_id`
columns deliberately have **no FK** so nothing can cascade into it).

1. **v2_ai_tutor_chats** — student_id FK, school_id FK, `source_type`
   (question|mistake|subtopic|topic|subject), question_id (index, no FK),
   student_mistake_id FK, denormalized subject/topic/subtopic ids, status
   (active|archived), title, last_message_at. Named index
   `v2_ai_tutor_chats_student_mistake_source_idx` (auto-name exceeded MySQL's
   64-char identifier limit — sqlite tests didn't catch it).
2. **v2_ai_tutor_messages** — chat_id FK, role (user|assistant), content
   (markdown), model, prompt_tokens, completion_tokens. Failed assistant
   turns are NOT stored (only logged); the user message survives for retry.
3. **v2_ai_tutor_context_items** — chat_id FK, message_id FK, item_type
   (question_stem|options|correct_answer|diagram_reference|worked_solution|
   option_explanation|flashcards|memcards|mermaid|interactive_widget|
   behaviour|recent_stats), ref_table, ref_id, meta json. Powers
   "which assets does the AI actually use" analytics without parsing prompts.
4. **v2_ai_tutor_quizzes** — chat_id FK, student_id FK, school_id,
   question_id (no FK), student_mistake_id FK, title, status
   (ready|attempted), model.
5. **v2_ai_tutor_quiz_questions** — quiz_id FK, sort_order, stem, options
   json `[{label,text}]`, correct_option, explanation.
6. **v2_ai_tutor_quiz_attempts** — quiz_id FK, student_id FK, school_id,
   answers json `{question_id: label}`, score, total, submitted_at.
   Learning signal ONLY — fully separate from official exam results.
7. **v2_ai_interaction_logs** — mirrors `v2_audit_logs` (docs rule 10):
   actor_type/actor_id/role(guard), feature (tutor_chat|tutor_quiz),
   **source_context_type mirrors the chosen tutor mode** (question →
   question_id, mistake → student_mistake_id — authorization still always
   runs through the mistake), provider, model, prompt/completion/total
   tokens, cost_usd decimal(10,6), latency_ms, status (ok|fallback|error),
   error, request_json (REDACTED summary: chat_id, source_type, question_id,
   message≤500 chars, history_len, context_keys — never the full context),
   response_json (truncated 8KB), school_id (cost attribution). Indexes:
   (actor_type,actor_id), (feature,created_at), (school_id,created_at).
   One row per AI call including failures.

Models: `App\Models\V2\{AiTutorChat, AiTutorMessage, AiTutorContextItem,
AiTutorQuiz, AiTutorQuizQuestion, AiTutorQuizAttempt, AiInteractionLog}`.
`AiTutorChat` + `AiTutorQuiz` use `HasHashid` (route-bound) + the same
`school` global scope as `StudentMistake` (cross-school probes 404 at
binding).

---

## 5. Key files

**Laravel**
- `app/Services/AI/TopicalAiClient.php` — the single HTTP seam to python-ai.
- `app/Services/AI/StudentTutorContextBuilder.php` — context contract + caps.
- `app/Services/AI/StudentTutorService.php` — openChat / sendMessage /
  generateQuiz / submitQuizAttempt orchestration + logging.
- `app/Services/AI/AiInteractionLogger.php` — AuditLogger-style writer.
- `app/Http/Controllers/V2/Student/AiTutorController.php` — thin JSON
  controller; guards re-checked per request.
- Routes (`routes/v2.php`, student group, throttled):
  `POST learning-hub/mistakes/{mistake}/tutor/chats` (20/min) ·
  `GET tutor/chats/{chat}` · `POST tutor/chats/{chat}/messages` (10/min) ·
  `POST tutor/chats/{chat}/quiz` (5/min) ·
  `POST tutor/quizzes/{quiz}/attempts` (20/min).
- `LearningHubController::studio()` passes the `tutor.open` URL as an Inertia
  prop; review.blade.php has an "Ask the AI Tutor" card → `studio?tab=tutor`.

**python-ai/** (`app/`)
- `main.py` (FastAPI + /health), `config.py` (pydantic-settings + PRICES map
  `{gpt-4o-mini: (0.15, 0.60)} $/MTok`, prefix-matched so dated variants
  price correctly), `deps.py` (X-Internal-Token → 401).
- `tutor/`: `router.py` (502 on provider/validation failure), `schemas.py`
  (contracts + quiz validation + stem-numbering strip), `prompts.py`
  (TUTOR_BEHAVIOUR + QUIZ_BEHAVIOUR + level header), `chat.py`, `quiz.py`
  (strict JSON + one repair retry).
- `providers/`: `base.py` (Provider ABC + ProviderResult), 
  `openai_provider.py` (ACTIVE), `anthropic_provider.py` (stub).
- `retrieval/ indexing/ analytics/` — documented V2 skeletons (embeddings,
  vector search, reranker, asset indexer, pdf extractor, learning signals).

**React** (`resources/js/learn/`)
- `Pages/Solution.jsx` — chat-app layout: left sidebar rail (Question,
  Simulator, Solution, Flashcards, Memcards, Flow, **AI Tutor**, JSON),
  full-width shell, `?tab=tutor` deep link, mobile ☰ slide-in drawer
  (borderless full-bleed content on ≤920px).
- `tutor/TutorPanel.jsx` — opens/resumes chats, mode toggle, timeline that
  merges messages + quizzes chronologically, quiz gate.
- `tutor/MessageList.jsx` — user bubbles right (bordered), assistant replies
  borderless plain text (ChatGPT style), `overflow-wrap:anywhere`.
- `tutor/QuizCard.jsx` — checkpoint quiz; collapsed score bar after attempt;
  restored attempted state from server.
- `tutor/Minimap.jsx` — marker strip inside the chat container's right edge,
  vertically centered; one marker per user prompt + per quiz (amber);
  hovering opens a ChatGPT-style panel listing every prompt preview;
  click-jumps; hidden ≤920px.
- `tutor/Composer.jsx` — ChatGPT pill: compact single row; grows with text
  (12-line cap desktop / 4-line mobile, then internal scroll); grown state =
  column with text region above the ↑ send circle (34px, SVG stroke arrow);
  mobile-only ⤢ fullscreen compose; **grow-only latch** (deriving "grown"
  from measured height in both directions caused an infinite render loop on
  wrap-boundary text — un-grows only on clear/send). The v2 theme's global
  `textarea:focus !important` accent ring is neutralized inside the pill.
- `tutor/tutor.css` + additions in `learn.css` (ls-app/ls-side/ls-nav +
  Notes-style scrollbars).

**Gotcha fixed app-wide:** blades mounting React need `@viteReactRefresh`
before `@vite` or pages render blank under the Vite DEV server (fine with
built assets). Added to `learn.blade.php` + `temp/widget-lab.blade.php`.

---

## 6. Safety rules enforced (docs/ai-context/ai/04)

1. `correct_answer` is authoritative — never contradicted (rule 1).
2. Own data only; other students never enter a prompt (rule 2; test-proven).
3. Trusted assets only via `visibleAsset()` (approved/edited) (rule 4).
4. Say-when-insufficient: no assets → "based only on the question context"
   (rule 5).
5. AI writes ONLY to v2_ai_tutor_* + logs — never exams, mistakes statuses,
   question bank, or learning assets (rules 8/9; exam-tables-untouched test).
6. Every call logged incl. failures (rule 10).
7. First-name-only PII; redacted request logging (rule 11).
8. Release gate re-checked on EVERY request, not just open (rule 12).

## 7. Tests & verification

- `tests/Feature/V2/AiTutorTest.php` (9) + `AiTutorQuizTest.php` (4):
  foreign-student 403, unreleased 404 (open AND message), idempotent open per
  source_type, inactive source types rejected, no-grounding 422,
  **context-payload-only-allowed-data** (Http::fake + assertSent: first name
  only, draft assets excluded, no signed URLs, no other student's data),
  message + context items persisted, logs on success AND failure, quiz
  stored without leaking answers, invalid quiz payload rejected+logged,
  attempt scored with exam tables count-identical, 409 re-attempt, foreign
  attempt blocked. All 39 V2 feature tests green (sqlite :memory:,
  DatabaseMigrations, raw DB graph builders — no factories).
- Live E2E verified in a real browser (demo student hira.bukhari@thesage.edu.pk):
  login → Learning Hub → mistake → studio → Socratic chat (grounded, level-
  correct) → quiz generate/answer/score → rows in v2_ai_interaction_logs.

## 8. Known limitations / V2 next

- No streaming (synchronous request; 90s client timeout must stay under
  PHP max_execution_time) — streaming is the V2 fix for long waits.
- gpt-4o-mini quiz answer-keys can rarely be inconsistent (self-check prompt
  mitigates; model swap via env is the lever).
- `firstOrCreate` race on double-open (no unique index by design — archived
  chats must be re-creatable).
- Retry after a failed send re-POSTs the kept user message (possible
  duplicate user rows — accepted).
- Subtopic/topic/subject chats, embeddings/RAG (python-ai skeleton dirs),
  learning-signal analytics, teacher visibility — all deferred.
