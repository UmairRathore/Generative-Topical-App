"""Tutor endpoints. Provider/parse failures surface as 502 so Laravel's
StudentTutorService logs status=error and shows the graceful retry UI."""

import logging
from functools import lru_cache

from fastapi import APIRouter, HTTPException

from ..config import get_settings
from ..providers.base import Provider
from ..providers.openai_provider import OpenAIProvider
from .chat import handle_chat
from .quiz import QuizGenerationError, handle_quiz
from .schemas import ChatRequest, ChatResponse, QuizRequest, QuizResponse

log = logging.getLogger("topicaled.tutor")

router = APIRouter(prefix="/tutor")


@lru_cache
def get_provider() -> Provider:
    return OpenAIProvider(get_settings())


@router.post("/chat", response_model=ChatResponse)
def chat(req: ChatRequest) -> ChatResponse:
    try:
        return handle_chat(req, get_provider())
    except Exception as e:  # provider/network errors -> 502 for Laravel to log
        log.warning("tutor chat failed (chat_ref=%s): %s", req.chat_ref, e)
        raise HTTPException(status_code=502, detail="provider_error") from e


@router.post("/quiz", response_model=QuizResponse)
def quiz(req: QuizRequest) -> QuizResponse:
    try:
        return handle_quiz(req, get_provider())
    except QuizGenerationError as e:
        log.warning("quiz generation invalid after retry (chat_ref=%s): %s", req.chat_ref, e)
        raise HTTPException(status_code=502, detail="quiz_invalid") from e
    except Exception as e:
        log.warning("tutor quiz failed (chat_ref=%s): %s", req.chat_ref, e)
        raise HTTPException(status_code=502, detail="provider_error") from e
