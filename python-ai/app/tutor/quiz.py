"""Mini-quiz generation: strict-JSON provider call, pydantic-validated, one
repair retry on invalid output, else a provider error for Laravel to log."""

import json
import time

from pydantic import ValidationError

from ..config import cost_usd
from ..providers.base import Provider, ProviderResult
from . import prompts
from .schemas import Meta, QuizModel, QuizRequest, QuizResponse

# 5 questions with options + short explanations fit comfortably under this.
QUIZ_MAX_TOKENS = 2000


class QuizGenerationError(Exception):
    pass


def handle_quiz(req: QuizRequest, provider: Provider) -> QuizResponse:
    t0 = time.monotonic()
    system = prompts.quiz_system(req.context, req.num_questions)
    messages = [{"role": "user", "content": f"Generate the {req.num_questions}-question quiz now."}]

    result = provider.complete(system=system, messages=messages, max_tokens=QUIZ_MAX_TOKENS, json_mode=True)
    usage = [result]

    try:
        quiz = _parse(result.text)
    except (json.JSONDecodeError, ValidationError) as first_error:
        # One repair attempt: show the model its own output and the error.
        retry = provider.complete(
            system=system,
            messages=[
                *messages,
                {"role": "assistant", "content": result.text},
                {"role": "user", "content": f"That JSON was invalid ({first_error}). Return corrected STRICT JSON only."},
            ],
            max_tokens=QUIZ_MAX_TOKENS,
            json_mode=True,
        )
        usage.append(retry)
        try:
            quiz = _parse(retry.text)
        except (json.JSONDecodeError, ValidationError) as second_error:
            raise QuizGenerationError(str(second_error)) from second_error

    return QuizResponse(quiz=quiz, meta=_meta(provider, usage, t0))


def _parse(text: str) -> QuizModel:
    return QuizModel.model_validate(json.loads(text))


def _meta(provider: Provider, usage: list[ProviderResult], t0: float) -> Meta:
    input_tokens = sum(u.input_tokens for u in usage) if all(u.input_tokens is not None for u in usage) else None
    output_tokens = sum(u.output_tokens for u in usage) if all(u.output_tokens is not None for u in usage) else None
    model = usage[-1].model
    return Meta(
        provider=provider.name,
        model=model,
        input_tokens=input_tokens,
        output_tokens=output_tokens,
        total_tokens=input_tokens + output_tokens if input_tokens is not None and output_tokens is not None else None,
        cost_usd=cost_usd(model, input_tokens, output_tokens),
        latency_ms=int((time.monotonic() - t0) * 1000),
    )
