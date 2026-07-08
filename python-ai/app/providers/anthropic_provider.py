"""Anthropic/Claude provider - future swap-in behind the same Provider seam.

Not active in V1. When implemented: use the official `anthropic` SDK's
Messages API (system + messages, usage.input_tokens/output_tokens) and add
the model's prices to config.PRICES.
"""

from ..config import Settings
from .base import Provider, ProviderResult


class AnthropicProvider(Provider):
    name = "anthropic"

    def __init__(self, settings: Settings):
        raise NotImplementedError("Anthropic provider is a V2 swap-in; OpenAI is the V1 default.")

    def complete(
        self,
        system: str,
        messages: list[dict],
        max_tokens: int = 2048,
        json_mode: bool = False,
    ) -> ProviderResult:
        raise NotImplementedError
