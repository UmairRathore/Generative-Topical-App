# python-ai — TopicalEd internal AI service

The AI pipeline for TopicalEd. **Laravel is the only caller** and the only
gatekeeper: this service has **no database access** and never authorizes
anything itself. It receives context that Laravel has already authorized
(ownership, school tenancy, result-release gates, asset visibility), formats
a grounded prompt, calls the provider, and returns the answer plus
token/cost/latency metadata for Laravel to log.

Do not add student-facing endpoints or DB credentials here — this service
must never become a second student backend.

## Run (dev)

```bash
cd python-ai
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
cp .env.example .env       # fill OPENAI_API_KEY and INTERNAL_TOKEN
.venv/bin/uvicorn app.main:app --port 9001
```

Laravel side (`.env`): `PYTHON_AI_URL=http://127.0.0.1:9001` and
`PYTHON_AI_TOKEN` = the same value as `INTERNAL_TOKEN` here.

The model is env-only (`OPENAI_MODEL`, default `gpt-4o-mini`). If the model is
ever unavailable, swap the env value — no code change.

## Endpoints

All `/tutor/*` endpoints require the `X-Internal-Token` header.

| Endpoint | Purpose |
|---|---|
| `GET /health` | Liveness + configured model (no auth) |
| `POST /tutor/chat` | Socratic tutor turn: `{chat_ref, context, history, message}` → `{ok, answer, meta}` |
| `POST /tutor/quiz` | 3–5 question mini quiz: `{chat_ref, context, num_questions}` → `{ok, quiz, meta}` |

`meta` carries `{provider, model, input_tokens, output_tokens, total_tokens,
cost_usd, latency_ms}`. Provider failures return `502 {ok: false, ...}`.

## Smoke test

```bash
curl -s localhost:9001/health

curl -s localhost:9001/tutor/chat \
  -H 'Content-Type: application/json' -H "X-Internal-Token: $INTERNAL_TOKEN" \
  -d '{"chat_ref":"smoke","context":{"student":{"first_name":"Ali"},
       "question":{"question_id":1,"stem":"What is 2+2?","options":[{"label":"A","text":"3"},{"label":"B","text":"4"}],
       "correct_answer":"B","diagram_references":[]},"grounding_assets":{}},
       "history":[],"message":"Why is A wrong?"}'
```

## Layout

```
app/
  main.py            FastAPI app + /health
  config.py          env settings (pydantic-settings) + price table
  deps.py            X-Internal-Token verification
  tutor/             chat + quiz handlers, prompts, request/response schemas
  providers/         provider seam: base.py, openai_provider.py (active),
                     anthropic_provider.py (stub — swap-in later)
  retrieval/         V2 skeleton: embeddings, vector search, reranking
  indexing/          V2 skeleton: asset indexing, PDF extraction
  analytics/         V2 skeleton: learning signals
```

`retrieval/`, `indexing/` and `analytics/` are deliberate skeletons — the
structure exists so embeddings/RAG can land next without reshaping the service.
