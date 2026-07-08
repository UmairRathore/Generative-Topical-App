"""Request/response contracts between Laravel and this service.

`context` stays a plain dict on purpose: Laravel owns the context contract
(docs/ai-context/ai/03) and may add fields; this service treats it as opaque,
pre-authorized data to ground the prompt with.
"""

import re
from typing import Literal

from pydantic import BaseModel, Field, field_validator, model_validator


class Turn(BaseModel):
    role: Literal["user", "assistant"]
    content: str


class Meta(BaseModel):
    provider: str
    model: str
    input_tokens: int | None = None
    output_tokens: int | None = None
    total_tokens: int | None = None
    cost_usd: float | None = None
    latency_ms: int | None = None


class ChatRequest(BaseModel):
    chat_ref: str = ""
    context: dict
    history: list[Turn] = Field(default_factory=list)
    message: str = Field(min_length=1, max_length=4000)


class ChatResponse(BaseModel):
    ok: bool = True
    answer: str
    meta: Meta


class QuizOption(BaseModel):
    label: str = Field(min_length=1, max_length=5)
    text: str


class QuizQuestion(BaseModel):
    stem: str = Field(min_length=1)
    options: list[QuizOption] = Field(min_length=2, max_length=5)
    correct_option: str
    explanation: str | None = None

    @field_validator("stem")
    @classmethod
    def strip_model_numbering(cls, v: str) -> str:
        # The UI numbers questions itself - drop any "1." / "2)" the model added.
        return re.sub(r"^\s*\d+\s*[\.\)]?\s+", "", v).strip()

    @model_validator(mode="after")
    def correct_option_in_labels(self) -> "QuizQuestion":
        labels = [o.label.upper() for o in self.options]
        if len(labels) != len(set(labels)):
            raise ValueError("option labels must be unique")
        if self.correct_option.upper() not in labels:
            raise ValueError("correct_option must match an option label")
        self.correct_option = self.correct_option.upper()
        return self


class QuizModel(BaseModel):
    title: str = "Quick check"
    questions: list[QuizQuestion] = Field(min_length=3, max_length=5)


class QuizRequest(BaseModel):
    chat_ref: str = ""
    context: dict
    num_questions: int = 4

    @field_validator("num_questions")
    @classmethod
    def clamp(cls, v: int) -> int:
        return max(3, min(5, v))


class QuizResponse(BaseModel):
    ok: bool = True
    quiz: QuizModel
    meta: Meta
