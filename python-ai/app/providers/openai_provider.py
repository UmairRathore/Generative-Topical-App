"""Active V1 provider: OpenAI via the official SDK. The model comes from env
(OPENAI_MODEL) - never hardcoded - so an unavailable model is swapped by
changing env alone. The SDK retries 429/5xx itself."""

from openai import OpenAI

from ..config import Settings
from .base import Provider, ProviderResult


class OpenAIProvider(Provider):
    name = "openai"

    def __init__(self, settings: Settings):
        self.client = OpenAI(api_key=settings.openai_api_key)
        self.model = settings.openai_model

    def complete(
        self,
        system: str,
        messages: list[dict],
        max_tokens: int = 2048,
        json_mode: bool = False,
    ) -> ProviderResult:
        kwargs: dict = {}
        if json_mode:
            # Quiz generation only - normal tutor chat returns plain markdown.
            kwargs["response_format"] = {"type": "json_object"}

        resp = self.client.chat.completions.create(
            model=self.model,
            max_tokens=max_tokens,
            messages=[{"role": "system", "content": system}, *messages],
            **kwargs,
        )

        return ProviderResult(
            text=resp.choices[0].message.content or "",
            model=resp.model,
            input_tokens=resp.usage.prompt_tokens if resp.usage else None,
            output_tokens=resp.usage.completion_tokens if resp.usage else None,
        )
