"""Provider seam. Handlers depend on this interface only, so the active
provider is swappable (env/config) without touching tutor logic."""

from abc import ABC, abstractmethod
from dataclasses import dataclass


@dataclass
class ProviderResult:
    text: str
    model: str
    input_tokens: int | None = None
    output_tokens: int | None = None


class Provider(ABC):
    """A single completion call. `json_mode` asks the provider to emit strict
    JSON (used by quiz generation only - normal chat returns markdown)."""

    name: str = "unknown"

    @abstractmethod
    def complete(
        self,
        system: str,
        messages: list[dict],
        max_tokens: int = 2048,
        json_mode: bool = False,
    ) -> ProviderResult: ...
