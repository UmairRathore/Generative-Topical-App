#!/usr/bin/env bash
# ── TopicalEd dev launcher ────────────────────────────────────────────────────
# One command for the whole stack (no Docker):
#   [python]  python-ai FastAPI      → http://127.0.0.1:9001
#   [laravel] php8.4 artisan serve   → http://127.0.0.1:8000
#   [vite]    npm run dev (HMR)      → http://127.0.0.1:5173
#
# Usage:  ./dev.sh              start everything
#         ./dev.sh --no-vite    skip the Vite dev server (use built assets)
#         PY_PORT=9101 PHP_PORT=8100 ./dev.sh   override ports
# Ctrl+C stops all services.

set -u
cd "$(dirname "$0")"

PY_PORT="${PY_PORT:-9001}"
PHP_PORT="${PHP_PORT:-8000}"
WITH_VITE=1
[ "${1:-}" = "--no-vite" ] && WITH_VITE=0

say()  { printf '\033[1;36m[dev]\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31m[dev]\033[0m %s\n' "$*" >&2; exit 1; }

port_free() { ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }

# ── Preflight ────────────────────────────────────────────────────────────────
command -v php8.4 >/dev/null || fail "php8.4 not found."

# Python setup is automatic: build the venv + deps on first run (or after deletion).
if [ ! -x python-ai/.venv/bin/uvicorn ]; then
    say "python-ai venv missing — building it (one-time, ~1 min)…"
    ( cd python-ai && python3 -m venv .venv && .venv/bin/pip install -q -r requirements.txt ) \
        || fail "venv setup failed. Is python3.12-venv installed? (sudo apt install python3.12-venv)"
fi
if [ ! -f python-ai/.env ]; then
    cp python-ai/.env.example python-ai/.env
    say "created python-ai/.env from example — fill in OPENAI_API_KEY + INTERNAL_TOKEN."
fi
grep -q '^OPENAI_API_KEY=..' python-ai/.env || say "WARNING: OPENAI_API_KEY is empty in python-ai/.env — tutor calls will fail with provider_error."
port_free "$PY_PORT"  || fail "Port $PY_PORT is already in use (python-ai already running?). Stop it or set PY_PORT."
port_free "$PHP_PORT" || fail "Port $PHP_PORT is already in use. Stop it or set PHP_PORT."

# npm comes via nvm in this WSL setup — source it if npm isn't already on PATH.
if [ "$WITH_VITE" = 1 ] && ! command -v npm >/dev/null; then
    export NVM_DIR="$HOME/.nvm"
    # shellcheck disable=SC1091
    [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
    command -v npm >/dev/null || { say "WARNING: npm not found — skipping Vite (built assets will be served)."; WITH_VITE=0; }
fi

# ── Launch ───────────────────────────────────────────────────────────────────
PIDS=()
cleanup() {
    trap - EXIT INT TERM
    say "shutting down…"
    for pid in "${PIDS[@]}"; do kill "$pid" 2>/dev/null; done
    wait 2>/dev/null
    say "all services stopped."
    exit 0
}
trap cleanup EXIT INT TERM

( cd python-ai && exec .venv/bin/uvicorn app.main:app --port "$PY_PORT" 2>&1 ) \
    | sed -u 's/^/\x1b[35m[python]\x1b[0m  /' & PIDS+=($!)

( exec php8.4 artisan serve --host=127.0.0.1 --port="$PHP_PORT" 2>&1 ) \
    | sed -u 's/^/\x1b[33m[laravel]\x1b[0m /' & PIDS+=($!)

if [ "$WITH_VITE" = 1 ]; then
    ( exec npm run dev 2>&1 ) | sed -u 's/^/\x1b[32m[vite]\x1b[0m    /' & PIDS+=($!)
fi

say "python-ai  → http://127.0.0.1:$PY_PORT  (health: /health)"
say "laravel    → http://127.0.0.1:$PHP_PORT"
[ "$WITH_VITE" = 1 ] && say "vite (HMR) → http://127.0.0.1:5173"
say "Ctrl+C stops everything."

wait
