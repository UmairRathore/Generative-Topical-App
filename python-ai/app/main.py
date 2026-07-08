"""TopicalEd internal AI service. Laravel-only caller; no DB access here."""

from fastapi import Depends, FastAPI

from .config import get_settings
from .deps import verify_internal_token
from .tutor.router import router as tutor_router

app = FastAPI(title="TopicalEd AI", docs_url=None, redoc_url=None)

app.include_router(tutor_router, dependencies=[Depends(verify_internal_token)])


@app.get("/health")
def health() -> dict:
    return {"ok": True, "model": get_settings().openai_model}
