"""Runtime configuration, read from environment variables (12-factor)."""
from __future__ import annotations

from functools import lru_cache
from pathlib import Path

from pydantic import field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

AGENTS_DIR = Path(__file__).resolve().parent.parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=AGENTS_DIR / ".env", env_file_encoding="utf-8", extra="ignore")

    app_env: str = "dev"
    log_level: str = "INFO"

    # Shared secret with the Symfony backend (header X-Internal-Token)
    internal_api_token: str = "change-me-internal-token"

    # PostgreSQL + pgvector. Empty => in-memory vector store (dev / tests only).
    database_url: str = ""
    # Read-only role used by the database_query tool (demo_* business tables only).
    business_database_url: str = ""
    # Redis for cross-process cancellation flags. Empty => in-process registry.
    redis_url: str = ""

    # Volume shared with Symfony where uploaded knowledge-base files live.
    storage_dir: Path = Path("../backend/var/storage")

    # LLM providers
    openai_api_key: str = ""
    openai_base_url: str = ""
    anthropic_api_key: str = ""
    ollama_base_url: str = ""
    default_provider: str = "mock"

    # Embeddings: auto | openai | ollama | hash
    embedding_provider: str = "auto"
    openai_embedding_model: str = "text-embedding-3-small"
    ollama_embedding_model: str = "nomic-embed-text"

    # Tools
    tavily_api_key: str = ""
    smtp_host: str = ""
    smtp_port: int = 1025
    smtp_user: str = ""
    smtp_password: str = ""
    smtp_from: str = "ZYNTARA Agent <agent@zyntara.local>"
    smtp_starttls: bool = False
    email_max_recipients: int = 5
    http_timeout_seconds: float = 20.0
    # Public APIs such as Wikipedia reject anonymous clients: include your deployment URL or a contact.
    http_user_agent: str = "ZyntaraAgent/1.0 (+http://localhost:8080)"

    # Execution guard-rails
    max_run_seconds: int = 600
    tool_timeout_seconds: float = 30.0
    rag_top_k: int = 5
    chunk_size: int = 800
    chunk_overlap: int = 120

    @field_validator("storage_dir")
    @classmethod
    def _absolute_storage(cls, value: Path) -> Path:
        # Relative paths are relative to the agents/ directory, whatever the working directory.
        return value if value.is_absolute() else (AGENTS_DIR / value).resolve()


@lru_cache
def get_settings() -> Settings:
    return Settings()
