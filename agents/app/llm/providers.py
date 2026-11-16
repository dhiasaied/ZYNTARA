"""LLM provider abstraction: one call site for OpenAI, Anthropic, Ollama and the offline mock."""
from __future__ import annotations

import logging
from typing import Any

import httpx
from langchain_core.language_models.chat_models import BaseChatModel

from app.config import Settings
from app.llm.mock import MockChatModel

log = logging.getLogger(__name__)


class ProviderNotConfigured(RuntimeError):
    """Raised when an agent targets a provider whose credentials are missing."""


OPENAI_MODELS = ["gpt-5", "gpt-5-mini", "gpt-4.1-mini"]
ANTHROPIC_MODELS = ["claude-opus-5-5", "claude-sonnet-5-5", "claude-haiku-5-5"]
OLLAMA_FALLBACK_MODELS = ["llama3.2", "qwen2.5", "mistral"]


def _ollama_models(settings: Settings) -> list[str]:
    try:
        response = httpx.get(settings.ollama_base_url.rstrip("/") + "/api/tags", timeout=2)
        response.raise_for_status()
        names = [m["name"] for m in response.json().get("models", [])]
        return names or OLLAMA_FALLBACK_MODELS
    except Exception:  # noqa: BLE001 - availability probe
        return []


def list_providers(settings: Settings) -> list[dict[str, Any]]:
    ollama_models = _ollama_models(settings) if settings.ollama_base_url else []
    return [
        {
            "id": "mock",
            "name": "ZYNTARA Mock (offline)",
            "available": True,
            "models": ["zyntara-mock-1"],
            "default_model": "zyntara-mock-1",
            "description": "Deterministic offline model to try the whole ladder without an API key.",
        },
        {
            "id": "openai",
            "name": "OpenAI",
            "available": bool(settings.openai_api_key),
            "models": OPENAI_MODELS,
            "default_model": OPENAI_MODELS[1],
            "description": "Set OPENAI_API_KEY (and optionally OPENAI_BASE_URL for compatible APIs).",
        },
        {
            "id": "anthropic",
            "name": "Anthropic Claude",
            "available": bool(settings.anthropic_api_key),
            "models": ANTHROPIC_MODELS,
            "default_model": ANTHROPIC_MODELS[1],
            "description": "Set ANTHROPIC_API_KEY.",
        },
        {
            "id": "ollama",
            "name": "Ollama (local)",
            "available": bool(ollama_models),
            "models": ollama_models or OLLAMA_FALLBACK_MODELS,
            "default_model": (ollama_models or OLLAMA_FALLBACK_MODELS)[0],
            "description": "Set OLLAMA_BASE_URL to a running Ollama server.",
        },
    ]


def get_chat_model(settings: Settings, provider: str, model: str, temperature: float = 0.3) -> BaseChatModel:
    provider = provider or settings.default_provider
    if provider == "mock":
        return MockChatModel(model_name=model or "zyntara-mock-1")

    if provider == "openai":
        if not settings.openai_api_key:
            raise ProviderNotConfigured('Provider "openai" is not configured (OPENAI_API_KEY is empty).')
        from langchain_openai import ChatOpenAI

        kwargs: dict[str, Any] = {"model": model, "api_key": settings.openai_api_key, "timeout": 120, "max_retries": 2,
                                  "stream_usage": True}
        if settings.openai_base_url:
            kwargs["base_url"] = settings.openai_base_url
        # Reasoning models only accept the default temperature.
        if not model.startswith(("o1", "o3", "o4", "gpt-5")):
            kwargs["temperature"] = temperature
        return ChatOpenAI(**kwargs)

    if provider == "anthropic":
        if not settings.anthropic_api_key:
            raise ProviderNotConfigured('Provider "anthropic" is not configured (ANTHROPIC_API_KEY is empty).')
        from langchain_anthropic import ChatAnthropic

        return ChatAnthropic(model=model, api_key=settings.anthropic_api_key, temperature=min(temperature, 1.0),
                             max_tokens=4096, timeout=120, max_retries=2)

    if provider == "ollama":
        if not settings.ollama_base_url:
            raise ProviderNotConfigured('Provider "ollama" is not configured (OLLAMA_BASE_URL is empty).')
        from langchain_ollama import ChatOllama

        return ChatOllama(model=model, base_url=settings.ollama_base_url, temperature=temperature)

    raise ProviderNotConfigured(f'Unknown provider "{provider}".')
