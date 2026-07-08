"""Environment-driven settings. The model is NEVER hardcoded in handlers or
providers - it always comes from OPENAI_MODEL so an unavailable model can be
swapped by changing env alone."""

from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict

# $ per 1M tokens (input, output). Unknown models log cost as None rather
# than guessing - keep this map in sync when swapping OPENAI_MODEL.
PRICES: dict[str, tuple[float, float]] = {
    "gpt-4o-mini": (0.15, 0.60),
}


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    openai_api_key: str = ""
    openai_model: str = "gpt-4o-mini"
    internal_token: str = ""
    port: int = 9001


@lru_cache
def get_settings() -> Settings:
    return Settings()


def cost_usd(model: str, input_tokens: int | None, output_tokens: int | None) -> float | None:
    """Best-effort cost from the price table; None when unknown."""
    # Price rows are keyed by model family prefix so dated variants still match.
    for key, (inp, out) in PRICES.items():
        if model and model.startswith(key) and input_tokens is not None and output_tokens is not None:
            return round(input_tokens * inp / 1_000_000 + output_tokens * out / 1_000_000, 6)
    return None
