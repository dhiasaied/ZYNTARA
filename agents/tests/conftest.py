import pytest

from app.config import Settings
from app.rag.embeddings import HashEmbedder
from app.rag.store import InMemoryVectorStore
from app.runtime.cancellation import CancellationRegistry
from app.runtime.context import RunContext
from app.runtime.events import EventEmitter


@pytest.fixture
def settings(tmp_path) -> Settings:
    return Settings(_env_file=None, storage_dir=tmp_path, internal_api_token="secret", database_url="", redis_url="",
                    openai_api_key="", anthropic_api_key="", ollama_base_url="", smtp_host="", tavily_api_key="")


@pytest.fixture
def ctx(settings) -> RunContext:
    emitter = EventEmitter(run_id="run-1", callback=None, cancellations=CancellationRegistry())
    return RunContext(settings=settings, store=InMemoryVectorStore(), embedder=HashEmbedder(), emitter=emitter, owner_id="u1")


def event_types(ctx: RunContext) -> list[str]:
    return [e["type"] for e in ctx.emitter.events]
