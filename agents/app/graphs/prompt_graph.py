"""PROMPT rung: a raw prompt sent straight to the LLM (the Playground)."""
from __future__ import annotations

from typing import Annotated, TypedDict

from langchain_core.messages import AIMessage, BaseMessage, HumanMessage
from langgraph.graph import END, START, StateGraph
from langgraph.graph.message import add_messages

from app.graphs.common import stream_model
from app.llm.providers import get_chat_model
from app.runtime.context import RunContext
from app.schemas import HistoryMessage, PromptSpec


class PromptState(TypedDict):
    messages: Annotated[list[BaseMessage], add_messages]


async def run_prompt(spec: PromptSpec, user_input: str, history: list[HistoryMessage], ctx: RunContext) -> str:
    model = get_chat_model(ctx.settings, spec.provider, spec.model, spec.temperature)
    actor = "LLM"

    async def llm(state: PromptState) -> dict:
        await ctx.emitter.checkpoint()
        return {"messages": [await stream_model(model, state["messages"], ctx, actor)]}

    graph = StateGraph(PromptState)
    graph.add_node("llm", llm)
    graph.add_edge(START, "llm")
    graph.add_edge("llm", END)

    messages: list[BaseMessage] = [HumanMessage(content=m.content) if m.role == "user" else AIMessage(content=m.content) for m in history]
    messages.append(HumanMessage(content=user_input))
    state = await graph.compile().ainvoke({"messages": messages})
    return str(state["messages"][-1].content)
