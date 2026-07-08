"""One Socratic tutor turn: system prompt from the pre-authorized context,
history + new message to the provider, answer + usage metadata back."""

import time

from ..config import cost_usd
from ..providers.base import Provider
from . import prompts
from .schemas import ChatRequest, ChatResponse, Meta

# Socratic replies are short by design (~180 words); this is a hard safety
# ceiling on spend, not a target.
CHAT_MAX_TOKENS = 700


def handle_chat(req: ChatRequest, provider: Provider) -> ChatResponse:
    t0 = time.monotonic()

    result = provider.complete(
        system=prompts.chat_system(req.context),
        messages=[*[t.model_dump() for t in req.history], {"role": "user", "content": req.message}],
        max_tokens=CHAT_MAX_TOKENS,
    )

    return ChatResponse(
        answer=result.text,
        meta=_meta(provider, result, t0),
    )


def _meta(provider: Provider, result, t0: float) -> Meta:
    total = (
        result.input_tokens + result.output_tokens
        if result.input_tokens is not None and result.output_tokens is not None
        else None
    )
    return Meta(
        provider=provider.name,
        model=result.model,
        input_tokens=result.input_tokens,
        output_tokens=result.output_tokens,
        total_tokens=total,
        cost_usd=cost_usd(result.model, result.input_tokens, result.output_tokens),
        latency_ms=int((time.monotonic() - t0) * 1000),
    )
