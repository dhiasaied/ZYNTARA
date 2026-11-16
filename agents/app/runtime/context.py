"""Per-run execution context shared by graphs and tools."""
from __future__ import annotations

from dataclasses import dataclass, field

from langchain_core.messages import AIMessage, BaseMessage

from app.config import Settings
from app.rag.embeddings import Embedder
from app.rag.store import VectorStore
from app.runtime.events import EventEmitter


@dataclass
class UsageTracker:
    input_tokens: int = 0
    output_tokens: int = 0

    def add(self, message: BaseMessage) -> None:
        usage = getattr(message, "usage_metadata", None) if isinstance(message, AIMessage) else None
        if usage:
            self.input_tokens += int(usage.get("input_tokens", 0) or 0)
            self.output_tokens += int(usage.get("output_tokens", 0) or 0)


@dataclass
class RunContext:
    settings: Settings
    store: VectorStore
    embedder: Embedder
    emitter: EventEmitter
    owner_id: str | None
    usage: UsageTracker = field(default_factory=UsageTracker)
    tools_used: list[str] = field(default_factory=list)
