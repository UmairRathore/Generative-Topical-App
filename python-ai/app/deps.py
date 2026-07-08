"""Internal auth: Laravel is the only caller, identified by a shared secret."""

from fastapi import Header, HTTPException

from .config import get_settings


def verify_internal_token(x_internal_token: str = Header(default="")) -> None:
    settings = get_settings()
    if not settings.internal_token or x_internal_token != settings.internal_token:
        raise HTTPException(status_code=401, detail="invalid internal token")
