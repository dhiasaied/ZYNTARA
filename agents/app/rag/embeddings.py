"""Embedding providers. The hashing embedder needs no network and keeps RAG working offline."""
from __future__ import annotations

import hashlib
import math
import re
from typing import Protocol

from app.config import Settings

TOKEN_RE = re.compile(r"\w+", re.UNICODE)


class Embedder(Protocol):
    model_name: str

    async def embed(self, texts: list[str]) -> list[list[float]]: ...


class HashEmbedder:
    """Feature-hashing of unigrams + bigrams, L2-normalised. Lexical, deterministic, multilingual."""

    def __init__(self, dim: int = 512) -> None:
        self.dim = dim
        self.model_name = f"hash-{dim}"

    def _vector(self, text: str) -> list[float]:
        tokens = [t.lower() for t in TOKEN_RE.findall(text)]
        features = tokens + [f"{a} {b}" for a, b in zip(tokens, tokens[1:])]
        vec = [0.0] * self.dim
        for feature in features:
            digest = hashlib.blake2b(feature.encode("utf-8"), digest_size=8).digest()
            index = int.from_bytes(digest[:4], "little") % self.dim
            vec[index] += 1.0 if digest[4] & 1 else -1.0
        norm = math.sqrt(sum(v * v for v in vec)) or 1.0
        return [v / norm for v in vec]

    async def embed(self, texts: list[str]) -> list[list[float]]:
        return [self._vector(t) for t in texts]


class OpenAIEmbedder:
    def __init__(self, settings: Settings) -> None:
        from langchain_openai import OpenAIEmbeddings

        kwargs = {"model": settings.openai_embedding_model, "api_key": settings.openai_api_key}
        if settings.openai_base_url:
            kwargs["base_url"] = settings.openai_base_url
        self._impl = OpenAIEmbeddings(**kwargs)
        self.model_name = f"openai:{settings.openai_embedding_model}"

    async def embed(self, texts: list[str]) -> list[list[float]]:
        return await self._impl.aembed_documents(texts)


class OllamaEmbedder:
    def __init__(self, settings: Settings) -> None:
        from langchain_ollama import OllamaEmbeddings

        self._impl = OllamaEmbeddings(model=settings.ollama_embedding_model, base_url=settings.ollama_base_url)
        self.model_name = f"ollama:{settings.ollama_embedding_model}"

    async def embed(self, texts: list[str]) -> list[list[float]]:
        return await self._impl.aembed_documents(texts)


def build_embedder(settings: Settings) -> Embedder:
    choice = settings.embedding_provider
    if choice == "openai" or (choice == "auto" and settings.openai_api_key):
        return OpenAIEmbedder(settings)
    if choice == "ollama" and settings.ollama_base_url:
        return OllamaEmbedder(settings)
    return HashEmbedder()
