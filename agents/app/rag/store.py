"""Vector storage for knowledge-base chunks and agent long-term memories.

PgVectorStore is used in every real deployment; InMemoryVectorStore only when no
DATABASE_URL is configured (local hacking, unit tests).
"""
from __future__ import annotations

import json
import logging
import math
import uuid
from dataclasses import dataclass, field
from typing import Any, Protocol

log = logging.getLogger(__name__)


class VectorStore(Protocol):
    async def init(self) -> None: ...
    async def close(self) -> None: ...
    async def upsert_document(self, kb_id: str, document_id: str, filename: str, chunks: list[str], embeddings: list[list[float]], model: str) -> int: ...
    async def delete(self, document_id: str | None = None, kb_id: str | None = None) -> int: ...
    async def search(self, kb_ids: list[str], embedding: list[float], model: str, k: int) -> list[dict[str, Any]]: ...
    async def add_memory(self, agent_id: str, owner_id: str | None, content: str, embedding: list[float], model: str) -> None: ...
    async def search_memories(self, agent_id: str, embedding: list[float], model: str, k: int) -> list[dict[str, Any]]: ...
    async def healthy(self) -> bool: ...


def _cosine(a: list[float], b: list[float]) -> float:
    if len(a) != len(b):
        return 0.0
    dot = sum(x * y for x, y in zip(a, b))
    na = math.sqrt(sum(x * x for x in a)) or 1.0
    nb = math.sqrt(sum(y * y for y in b)) or 1.0
    return dot / (na * nb)


@dataclass
class InMemoryVectorStore:
    chunks: list[dict[str, Any]] = field(default_factory=list)
    memories: list[dict[str, Any]] = field(default_factory=list)

    async def init(self) -> None:
        return None

    async def close(self) -> None:
        return None

    async def healthy(self) -> bool:
        return True

    async def upsert_document(self, kb_id, document_id, filename, chunks, embeddings, model) -> int:
        await self.delete(document_id=document_id)
        for i, (content, emb) in enumerate(zip(chunks, embeddings)):
            self.chunks.append({"knowledge_base_id": kb_id, "document_id": document_id, "filename": filename,
                                "chunk_index": i, "content": content, "embedding": emb, "model": model})
        return len(chunks)

    async def delete(self, document_id=None, kb_id=None) -> int:
        before = len(self.chunks)
        self.chunks = [c for c in self.chunks if not ((document_id and c["document_id"] == document_id) or (kb_id and c["knowledge_base_id"] == kb_id))]
        return before - len(self.chunks)

    async def search(self, kb_ids, embedding, model, k) -> list[dict[str, Any]]:
        scored = [
            {**{key: c[key] for key in ("content", "document_id", "filename", "chunk_index", "knowledge_base_id")}, "score": round(_cosine(c["embedding"], embedding), 4)}
            for c in self.chunks if c["knowledge_base_id"] in kb_ids and c["model"] == model
        ]
        return sorted(scored, key=lambda r: -r["score"])[:k]

    async def add_memory(self, agent_id, owner_id, content, embedding, model) -> None:
        self.memories.append({"agent_id": agent_id, "content": content, "embedding": embedding, "model": model})

    async def search_memories(self, agent_id, embedding, model, k) -> list[dict[str, Any]]:
        scored = [{"content": m["content"], "score": round(_cosine(m["embedding"], embedding), 4)}
                  for m in self.memories if m["agent_id"] == agent_id and m["model"] == model]
        return sorted(scored, key=lambda r: -r["score"])[:k]


def _vec(values: list[float]) -> str:
    return "[" + ",".join(f"{v:.6f}" for v in values) + "]"


class PgVectorStore:
    SCHEMA = [
        "CREATE EXTENSION IF NOT EXISTS vector",
        """CREATE TABLE IF NOT EXISTS kb_chunks (
            id UUID PRIMARY KEY,
            knowledge_base_id UUID NOT NULL,
            document_id UUID NOT NULL,
            filename TEXT NOT NULL,
            chunk_index INT NOT NULL,
            content TEXT NOT NULL,
            embedding vector NOT NULL,
            embedding_model TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )""",
        "CREATE INDEX IF NOT EXISTS kb_chunks_kb_idx ON kb_chunks (knowledge_base_id, embedding_model)",
        "CREATE INDEX IF NOT EXISTS kb_chunks_doc_idx ON kb_chunks (document_id)",
        """CREATE TABLE IF NOT EXISTS agent_memories (
            id UUID PRIMARY KEY,
            agent_id UUID NOT NULL,
            owner_id UUID,
            content TEXT NOT NULL,
            embedding vector NOT NULL,
            embedding_model TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )""",
        "CREATE INDEX IF NOT EXISTS agent_memories_agent_idx ON agent_memories (agent_id, embedding_model)",
    ]

    def __init__(self, dsn: str) -> None:
        from psycopg_pool import AsyncConnectionPool

        self._pool = AsyncConnectionPool(dsn, min_size=1, max_size=10, open=False, kwargs={"autocommit": True})

    async def init(self) -> None:
        await self._pool.open()
        async with self._pool.connection() as conn:
            for statement in self.SCHEMA:
                await conn.execute(statement)

    async def close(self) -> None:
        await self._pool.close()

    async def healthy(self) -> bool:
        try:
            async with self._pool.connection() as conn:
                await conn.execute("SELECT 1")
            return True
        except Exception:  # noqa: BLE001
            return False

    async def upsert_document(self, kb_id, document_id, filename, chunks, embeddings, model) -> int:
        async with self._pool.connection() as conn:
            async with conn.transaction():
                await conn.execute("DELETE FROM kb_chunks WHERE document_id = %s", (document_id,))
                async with conn.cursor() as cur:
                    await cur.executemany(
                        "INSERT INTO kb_chunks (id, knowledge_base_id, document_id, filename, chunk_index, content, embedding, embedding_model) "
                        "VALUES (%s, %s, %s, %s, %s, %s, %s::vector, %s)",
                        [(uuid.uuid4(), kb_id, document_id, filename, i, c, _vec(e), model) for i, (c, e) in enumerate(zip(chunks, embeddings))],
                    )
        return len(chunks)

    async def delete(self, document_id=None, kb_id=None) -> int:
        async with self._pool.connection() as conn:
            if document_id:
                cur = await conn.execute("DELETE FROM kb_chunks WHERE document_id = %s", (document_id,))
            elif kb_id:
                cur = await conn.execute("DELETE FROM kb_chunks WHERE knowledge_base_id = %s", (kb_id,))
            else:
                return 0
            return cur.rowcount

    async def search(self, kb_ids, embedding, model, k) -> list[dict[str, Any]]:
        async with self._pool.connection() as conn:
            cur = await conn.execute(
                "SELECT content, document_id::text, filename, chunk_index, knowledge_base_id::text, 1 - (embedding <=> %s::vector) AS score "
                "FROM kb_chunks WHERE knowledge_base_id = ANY(%s::uuid[]) AND embedding_model = %s "
                "ORDER BY embedding <=> %s::vector LIMIT %s",
                (_vec(embedding), kb_ids, model, _vec(embedding), k),
            )
            rows = await cur.fetchall()
        return [
            {"content": r[0], "document_id": r[1], "filename": r[2], "chunk_index": r[3], "knowledge_base_id": r[4], "score": round(float(r[5]), 4)}
            for r in rows
        ]

    async def add_memory(self, agent_id, owner_id, content, embedding, model) -> None:
        async with self._pool.connection() as conn:
            await conn.execute(
                "INSERT INTO agent_memories (id, agent_id, owner_id, content, embedding, embedding_model) VALUES (%s, %s, %s, %s, %s::vector, %s)",
                (uuid.uuid4(), agent_id, owner_id, content, _vec(embedding), model),
            )

    async def search_memories(self, agent_id, embedding, model, k) -> list[dict[str, Any]]:
        async with self._pool.connection() as conn:
            cur = await conn.execute(
                "SELECT content, 1 - (embedding <=> %s::vector) FROM agent_memories WHERE agent_id = %s AND embedding_model = %s "
                "ORDER BY embedding <=> %s::vector LIMIT %s",
                (_vec(embedding), agent_id, model, _vec(embedding), k),
            )
            rows = await cur.fetchall()
        return [{"content": r[0], "score": round(float(r[1]), 4)} for r in rows]


def build_store(database_url: str) -> VectorStore:
    if not database_url:
        log.warning("DATABASE_URL is empty: using the in-memory vector store (data is lost on restart).")
        return InMemoryVectorStore()
    return PgVectorStore(database_url)


def to_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False)
