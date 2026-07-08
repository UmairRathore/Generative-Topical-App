"""Reranking (V2): re-order vector-search candidates for relevance before
they enter the tutor context window."""


def rerank(query: str, candidates: list[dict], top_k: int = 4) -> list[dict]:
    raise NotImplementedError("Reranking is a V2 feature - not part of Tutor V1.")
