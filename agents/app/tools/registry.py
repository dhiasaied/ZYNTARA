"""Builds the LangChain tool set for one agent in one run.

Tool keys exposed to users must match backend/src/Service/ToolCatalog.php. knowledge_search
and the memory tools are attached automatically from the agent configuration.
"""
from __future__ import annotations

import asyncio
from typing import Any

from langchain_core.tools import BaseTool, StructuredTool
from pydantic import BaseModel, Field

from app.rag.embeddings import Embedder
from app.runtime.context import RunContext
from app.schemas import AgentSpec
from app.tools import builtin

USER_TOOLS = {"send_email", "web_search", "database_query", "calculator", "current_datetime"}


class KnowledgeInput(BaseModel):
    query: str = Field(description="Question or keywords to look up in the attached documents", max_length=500)


class MemorySaveInput(BaseModel):
    fact: str = Field(description="A durable fact about the user or task worth remembering", max_length=1000)


class MemoryRecallInput(BaseModel):
    query: str = Field(description="What to recall", max_length=300)


def format_sources(results: list[dict[str, Any]]) -> str:
    if not results:
        return "No relevant passage found in the knowledge base."
    return "\n\n".join(f"[{r['filename']} #{r['chunk_index'] + 1} · score {r['score']}]\n{r['content']}" for r in results)


async def retrieve(ctx: RunContext, embedder: Embedder, kb_ids: list[str], query: str, k: int | None = None) -> list[dict[str, Any]]:
    [embedding] = await embedder.embed([query])
    return await ctx.store.search(kb_ids, embedding, embedder.model_name, k or ctx.settings.rag_top_k)


def _wrap(ctx: RunContext, name: str, description: str, schema: type[BaseModel], fn: Any) -> BaseTool:
    timeout = ctx.settings.tool_timeout_seconds

    async def guarded(**kwargs: Any) -> str:
        try:
            return await asyncio.wait_for(fn(**kwargs), timeout=timeout)
        except asyncio.TimeoutError:
            return f"Error: tool '{name}' timed out after {timeout:.0f}s."
        except Exception as exc:  # noqa: BLE001 - surfaced to the model, never crash the run
            return f"Error while running {name}: {exc}"

    return StructuredTool.from_function(coroutine=guarded, name=name, description=description, args_schema=schema)


def build_tools(agent: AgentSpec, ctx: RunContext, embedder: Embedder) -> list[BaseTool]:
    settings = ctx.settings
    tools: list[BaseTool] = []
    enabled = [t for t in agent.tools if t in USER_TOOLS]

    if "web_search" in enabled:
        tools.append(_wrap(ctx, "web_search", "Search the web for up-to-date information. Returns titles, URLs and snippets.",
                           builtin.WebSearchInput, builtin.make_web_search(settings)))
    if "send_email" in enabled:
        tools.append(_wrap(ctx, "send_email", "Send a plain-text email. Only call it when the user clearly asked to send.",
                           builtin.EmailInput, builtin.make_send_email(settings)))
    if "database_query" in enabled:
        tools.append(_wrap(ctx, "database_query", builtin.DB_DESCRIPTION, builtin.DatabaseInput, builtin.make_database_query(settings)))
    if "calculator" in enabled:
        tools.append(_wrap(ctx, "calculator", "Evaluate an arithmetic expression exactly.", builtin.CalculatorInput, builtin.calculator))
    if "current_datetime" in enabled:
        tools.append(_wrap(ctx, "current_datetime", "Get the current date and time in a timezone.", builtin.DateTimeInput, builtin.current_datetime))

    if agent.knowledge_base_ids:
        async def knowledge_search(query: str) -> str:
            return format_sources(await retrieve(ctx, embedder, agent.knowledge_base_ids, query))

        tools.append(_wrap(ctx, "knowledge_search",
                           "Search the documents attached to this agent. Answer ONLY from what it returns and cite the [file #chunk].",
                           KnowledgeInput, knowledge_search))

    if agent.memory_enabled:
        async def save_memory(fact: str) -> str:
            [embedding] = await embedder.embed([fact])
            await ctx.store.add_memory(agent.id, ctx.owner_id, fact, embedding, embedder.model_name)
            return f"Saved to long-term memory: {fact}"

        async def recall_memory(query: str) -> str:
            [embedding] = await embedder.embed([query])
            found = await ctx.store.search_memories(agent.id, embedding, embedder.model_name, 5)
            return "\n".join(f"- {m['content']}" for m in found) or "Nothing relevant in memory."

        tools.append(_wrap(ctx, "save_memory", "Store a durable fact for future conversations.", MemorySaveInput, save_memory))
        tools.append(_wrap(ctx, "recall_memory", "Recall facts saved in earlier conversations.", MemoryRecallInput, recall_memory))

    return tools
