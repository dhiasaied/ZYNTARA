"""Single-agent graph covering four rungs of the ladder.

    APP    : prompt + model                                   prepare → agent → END
    TOOLS  : + tools (bounded tool rounds)                    prepare → agent ⇄ tools → END
    RAG    : + documents retrieved up-front and on demand     prepare(retrieval) → agent ⇄ tools → END
    AGENT  : autonomous plan → act loop → self-reflection     prepare → plan → agent ⇄ tools → reflect ↺ → END
"""
from __future__ import annotations

import asyncio
import json
from typing import Annotated, Any, TypedDict

from langchain_core.messages import BaseMessage, HumanMessage, SystemMessage, ToolMessage
from langgraph.graph import END, START, StateGraph
from langgraph.graph.message import add_messages

from app.graphs.common import invoke_model, last_answer, stream_model
from app.llm.providers import get_chat_model
from app.runtime.context import RunContext
from app.schemas import AgentSpec, HistoryMessage
from app.tools.registry import build_tools, format_sources, retrieve

NON_AUTONOMOUS_TOOL_ROUNDS = 2
MAX_REFLECTIONS = 1


class AgentState(TypedDict):
    messages: Annotated[list[BaseMessage], add_messages]
    iterations: int
    reflections: int


def _system_prompt(agent: AgentSpec, has_tools: bool, context: str, memories: list[str]) -> str:
    parts = [agent.system_prompt.strip()]
    if has_tools:
        parts.append("You can call tools. Use them whenever they give a more reliable answer than guessing; never invent tool results.")
    if agent.knowledge_base_ids:
        parts.append(
            "Answer ONLY from the provided documents (context below and the knowledge_search tool). "
            "If the answer is not in them, say you don't know. Cite sources as [file #chunk]."
        )
    if agent.autonomous:
        parts.append("You are autonomous: follow your plan step by step, decide which tools to use, and stop when the task is complete.")
    if memories:
        parts.append("Long-term memory (facts from earlier conversations):\n" + "\n".join(f"- {m}" for m in memories))
    if context:
        parts.append(f"<context>\n{context}\n</context>")
    return "\n\n".join(parts)


def build_agent_graph(agent: AgentSpec, ctx: RunContext, actor: str | None = None):
    actor = actor or agent.name
    model = get_chat_model(ctx.settings, agent.provider, agent.model, agent.temperature)
    tools = build_tools(agent, ctx, ctx.embedder)
    tools_by_name = {t.name: t for t in tools}
    model_with_tools = model.bind_tools(tools) if tools else model
    tool_cap = agent.max_iterations if agent.autonomous else min(agent.max_iterations, NON_AUTONOMOUS_TOOL_ROUNDS)

    async def plan(state: AgentState) -> dict[str, Any]:
        await ctx.emitter.checkpoint()
        task = next(m for m in reversed(state["messages"]) if isinstance(m, HumanMessage)).content
        tool_list = ", ".join(tools_by_name) or "none"
        text = await invoke_model(model, [
            SystemMessage(content=agent.system_prompt),
            HumanMessage(content=f"Task: {task}\n\nAvailable tools: {tool_list}.\nReturn a numbered plan of at most 5 short steps. No other text."),
        ], ctx)
        await ctx.emitter.emit("plan", actor, text)
        return {"messages": [HumanMessage(content=f"Plan to follow:\n{text}\n\nExecute it now, using tools when useful, then give the final answer.")]}

    async def act(state: AgentState) -> dict[str, Any]:
        await ctx.emitter.checkpoint()
        message = await stream_model(model_with_tools, state["messages"], ctx, actor)
        if message.tool_calls:
            if message.content:
                await ctx.emitter.emit("thought", actor, str(message.content))
            for call in message.tool_calls:
                await ctx.emitter.emit("tool_call", actor, f"{call['name']}({json.dumps(call['args'], ensure_ascii=False)[:500]})",
                                       {"tool": call["name"], "args": call["args"], "call_id": call["id"]})
        return {"messages": [message]}

    async def run_tools(state: AgentState) -> dict[str, Any]:
        await ctx.emitter.checkpoint()
        calls = state["messages"][-1].tool_calls

        async def run_one(call: dict[str, Any]) -> ToolMessage:
            tool = tools_by_name.get(call["name"])
            if tool is None:
                output = f"Error: unknown tool '{call['name']}'."
            else:
                output = str(await tool.ainvoke(call["args"]))
                ctx.tools_used.append(call["name"])
            await ctx.emitter.emit("tool_result", actor, output[:4000], {"tool": call["name"], "call_id": call["id"]})
            return ToolMessage(content=output, tool_call_id=call["id"], name=call["name"])

        results = await asyncio.gather(*(run_one(c) for c in calls))
        return {"messages": list(results), "iterations": state["iterations"] + 1}

    async def finalize(state: AgentState) -> dict[str, Any]:
        await ctx.emitter.checkpoint()
        await ctx.emitter.emit("thought", actor, f"Tool budget of {tool_cap} round(s) reached — answering with what I have.")
        # Close the open tool calls so providers accept the transcript, then answer without tools.
        pending = [ToolMessage(content="Skipped: tool budget exhausted.", tool_call_id=c["id"], name=c["name"])
                   for c in state["messages"][-1].tool_calls]
        message = await stream_model(model, [*state["messages"], *pending, HumanMessage(content="Give your final answer now.")], ctx, actor)
        return {"messages": [*pending, message]}

    async def reflect(state: AgentState) -> dict[str, Any]:
        await ctx.emitter.checkpoint()
        task = next(m for m in state["messages"] if isinstance(m, HumanMessage)).content
        draft = last_answer(state["messages"])
        verdict = await invoke_model(model, [
            SystemMessage(content="You are a demanding reviewer of an AI agent's work."),
            HumanMessage(content=f"Task: {task}\n\nDraft answer:\n{draft}\n\nReply with APPROVED if the draft fully and correctly completes the task. "
                                 "Otherwise reply REVISE: followed by what is missing."),
        ], ctx)
        await ctx.emitter.emit("reflection", actor, verdict)
        if verdict.upper().startswith("REVISE") and state["reflections"] < MAX_REFLECTIONS:
            return {"messages": [HumanMessage(content=f"Reviewer feedback: {verdict}\nImprove your answer accordingly.")],
                    "reflections": state["reflections"] + 1}
        return {"reflections": MAX_REFLECTIONS + 1}

    def after_act(state: AgentState) -> str:
        last = state["messages"][-1]
        if getattr(last, "tool_calls", None):
            return "tools" if state["iterations"] < tool_cap else "finalize"
        return "reflect" if agent.autonomous and state["reflections"] <= MAX_REFLECTIONS else END

    def after_reflect(state: AgentState) -> str:
        return "agent" if state["reflections"] <= MAX_REFLECTIONS and isinstance(state["messages"][-1], HumanMessage) else END

    graph = StateGraph(AgentState)
    graph.add_node("agent", act)
    graph.add_node("tools", run_tools)
    graph.add_node("finalize", finalize)
    if agent.autonomous:
        graph.add_node("plan", plan)
        graph.add_node("reflect", reflect)
        graph.add_edge(START, "plan")
        graph.add_edge("plan", "agent")
        graph.add_conditional_edges("agent", after_act, ["tools", "finalize", "reflect", END])
        graph.add_conditional_edges("reflect", after_reflect, ["agent", END])
    else:
        graph.add_edge(START, "agent")
        graph.add_conditional_edges("agent", after_act, ["tools", "finalize", END])
    graph.add_edge("tools", "agent")
    graph.add_edge("finalize", END)
    return graph.compile(), tool_cap, bool(tools)


async def run_agent(agent: AgentSpec, user_input: str, history: list[HistoryMessage], ctx: RunContext, actor: str | None = None) -> str:
    """Runs one agent to completion and returns its final answer."""
    actor = actor or agent.name
    graph, tool_cap, has_tools = build_agent_graph(agent, ctx, actor)

    # RAG rung: retrieve up-front so the answer is grounded even if the model never calls the tool.
    context = ""
    if agent.knowledge_base_ids and not agent.autonomous:
        results = await retrieve(ctx, ctx.embedder, agent.knowledge_base_ids, user_input)
        context = format_sources(results) if results else ""
        await ctx.emitter.emit(
            "retrieval", actor,
            f"Retrieved {len(results)} passage(s) from the knowledge base." if results else "No relevant passage found in the knowledge base.",
            {"sources": [{"filename": r["filename"], "chunk": r["chunk_index"] + 1, "score": r["score"], "preview": r["content"][:240]} for r in results]},
        )

    memories: list[str] = []
    if agent.memory_enabled:
        [embedding] = await ctx.embedder.embed([user_input])
        memories = [m["content"] for m in await ctx.store.search_memories(agent.id, embedding, ctx.embedder.model_name, 3) if m["score"] > 0.2]

    messages: list[BaseMessage] = [SystemMessage(content=_system_prompt(agent, has_tools, context, memories))]
    for item in history:
        messages.append(HumanMessage(content=item.content) if item.role == "user" else _ai(item.content))
    messages.append(HumanMessage(content=user_input))

    state = await graph.ainvoke(
        {"messages": messages, "iterations": 0, "reflections": 0},
        config={"recursion_limit": tool_cap * 3 + 20},
    )
    return last_answer(state["messages"]) or "I could not produce an answer."


def _ai(content: str):
    from langchain_core.messages import AIMessage

    return AIMessage(content=content)
