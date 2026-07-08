"""Embedding pipeline (V2): embed syllabus topics/subtopics, approved learning
assets and (with consent rules) student notes for retrieval."""


def embed_texts(texts: list[str]) -> list[list[float]]:
    raise NotImplementedError("Embeddings are a V2 feature - not part of Tutor V1.")
