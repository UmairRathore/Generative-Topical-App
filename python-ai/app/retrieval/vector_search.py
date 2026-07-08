"""Vector search (V2): nearest-neighbour lookup over embedded curriculum
content. Store choice (pgvector vs service) is deliberately undecided."""


def search(query_embedding: list[float], top_k: int = 8) -> list[dict]:
    raise NotImplementedError("Vector search is a V2 feature - not part of Tutor V1.")
