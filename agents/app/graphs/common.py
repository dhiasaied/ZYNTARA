"""Helpers shared by the graphs."""
from __future__ import annotations

from typing import Any

from langchain_core.language_models.chat_models import BaseChatModel
from langchain_core.messages import AIMessage, AIMessageChunk, BaseMessage

from app.runtime.context import RunContext


def content_text(content: Any) -> str:
    """Normalises provider-specific content (str or list of blocks) to plain text."""
    if isinstance(content, str):
        return content
    if isinstance(content, list):
        return "".join(
            part if isinstance(part, str) else part.get("text", "") if isinstance(part, dict) and part.get("type") in (None, "text") else ""
            for part in content
        )
    return str(content or "")


async def stream_model(model: BaseChatModel | Any, messages: list[BaseMessage], ctx: RunContext, actor: str) -> AIMessage:
    """Streams a completion, forwarding text deltas live, and returns the assembled message."""
    full: AIMessageChunk | None = None
    async for chunk in model.astream(messages):
        await ctx.emitter.token(actor, content_text(chunk.content))
        full = chunk if full is None else full + chunk
    await ctx.emitter.flush_tokens()
    if full is None:
        full = AIMessageChunk(content="")
    message = AIMessage(
        content=content_text(full.content),
        tool_calls=list(full.tool_calls or []),
        usage_metadata=full.usage_metadata,
    )
    ctx.usage.add(message)
    return message


async def invoke_model(model: BaseChatModel, messages: list[BaseMessage], ctx: RunContext) -> str:
    """Non-streamed call for internal reasoning (planning, reflection, routing)."""
    message = await model.ainvoke(messages)
    ctx.usage.add(message)
    return content_text(message.content).strip()


def last_answer(messages: list[BaseMessage]) -> str:
    for message in reversed(messages):
        if isinstance(message, AIMessage) and not message.tool_calls and content_text(message.content).strip():
            return content_text(message.content).strip()
    return ""
