"""AGENTIC rung: several agents collaborating on one goal.

    supervisor ──► member (runs that member's own agent graph) ──► supervisor ... ──► synthesize ──► END

`supervisor` strategy: an LLM decides who acts next (or FINISH) after every contribution.
`sequential` strategy: members act in their configured order, each building on the previous work.
"""
from __future__ import annotations

import json
import re
from typing import Any, TypedDict

from langchain_core.messages import HumanMessage, SystemMessage
from langgraph.graph import END, START, StateGraph

from app.graphs.agent_graph import run_agent
from app.graphs.common import invoke_model, stream_model
from app.llm.providers import get_chat_model
from app.runtime.context import RunContext
from app.schemas import CrewMemberSpec, CrewSpec

SUPERVISOR = "Supervisor"
FINISH = "FINISH"


class Contribution(TypedDict):
    role: str
    agent: str
    output: str


class CrewState(TypedDict):
    round: int
    next: str
    instruction: str
    contributions: list[Contribution]


def _work_so_far(contributions: list[Contribution], limit: int = 1500) -> str:
    if not contributions:
        return "(nothing yet)"
    return "\n\n".join(f"[{c['role']} — {c['agent']}]\n{c['output'][:limit]}" for c in contributions)


def _parse_decision(text: str, roles: list[str]) -> tuple[str, str] | None:
    match = re.search(r"\{.*\}", text, flags=re.S)
    if not match:
        return None
    try:
        data = json.loads(match.group(0))
    except json.JSONDecodeError:
        return None
    nxt = str(data.get("next", "")).strip()
    lookup = {r.lower(): r for r in roles}
    if nxt.upper() == FINISH:
        return FINISH, str(data.get("instruction", ""))
    if nxt.lower() in lookup:
        return lookup[nxt.lower()], str(data.get("instruction", ""))
    return None


async def run_crew(crew: CrewSpec, user_input: str, ctx: RunContext) -> str:
    members: dict[str, CrewMemberSpec] = {m.role: m for m in crew.members}
    roles = list(members)
    supervisor_model = get_chat_model(ctx.settings, crew.supervisor.provider, crew.supervisor.model, 0.2)

    async def supervisor(state: CrewState) -> dict[str, Any]:
        await ctx.emitter.checkpoint()
        acted = [c["role"] for c in state["contributions"]]

        if state["round"] >= crew.max_rounds:
            decision: tuple[str, str] = (FINISH, f"Round limit ({crew.max_rounds}) reached.")
        elif crew.strategy == "sequential":
            idx = state["round"]
            decision = (roles[idx], f"Do your part as the {roles[idx]}, building on your teammates' work.") if idx < len(roles) else (FINISH, "Every member contributed.")
        else:
            team = "\n".join(f"- ROLE: {r} | agent: {m.agent.name} | tools: {', '.join(m.agent.tools) or 'none'} | rung: "
                             f"{'agent' if m.agent.autonomous else 'rag' if m.agent.knowledge_base_ids else 'tools' if m.agent.tools else 'app'}"
                             for r, m in members.items())
            prompt = (
                "SUPERVISOR_DECISION\n"
                "You coordinate a team of AI agents to achieve a goal. Delegate one step at a time.\n"
                f"Goal: {crew.goal}\nUser request: {user_input}\n\nTeam:\n{team}\n"
                f"ACTED: {', '.join(acted)}\n\nWork so far:\n{_work_so_far(state['contributions'])}\n\n"
                'Reply ONLY with JSON: {"next": "<ROLE or FINISH>", "instruction": "<precise instruction for that member>"}. '
                "Choose FINISH as soon as the work so far fully achieves the goal."
            )
            text = await invoke_model(supervisor_model, [SystemMessage(content="You are the crew supervisor."), HumanMessage(content=prompt)], ctx)
            parsed = _parse_decision(text, roles)
            if parsed is None:  # unparseable: fall back to the next member that has not acted yet
                pending = [r for r in roles if r not in acted]
                parsed = (pending[0], "Contribute your part towards the goal.") if pending else (FINISH, "Everyone contributed.")
            decision = parsed

        nxt, instruction = decision
        await ctx.emitter.emit("handoff", SUPERVISOR, f"→ {nxt}: {instruction}" if nxt != FINISH else f"→ FINISH: {instruction}",
                               {"to": nxt, "round": state["round"], "instruction": instruction})
        return {"next": nxt, "instruction": instruction}

    async def member(state: CrewState) -> dict[str, Any]:
        await ctx.emitter.checkpoint()
        spec = members[state["next"]]
        task = (
            f"Team goal: {crew.goal}\nOriginal request: {user_input}\nYour role in the team: {spec.role}\n"
            f"Instruction from the supervisor: {state['instruction']}\n\nWork so far by teammates:\n{_work_so_far(state['contributions'])}"
        )
        output = await run_agent(spec.agent, task, [], ctx, actor=spec.agent.name)
        await ctx.emitter.emit("thought", spec.agent.name, output[:6000], {"member_output": True, "role": spec.role})
        contribution: Contribution = {"role": spec.role, "agent": spec.agent.name, "output": output}
        return {"contributions": [*state["contributions"], contribution], "round": state["round"] + 1}

    def route(state: CrewState) -> str:
        return END if state["next"] == FINISH else "member"

    graph = StateGraph(CrewState)
    graph.add_node("supervisor", supervisor)
    graph.add_node("member", member)
    graph.add_edge(START, "supervisor")
    graph.add_conditional_edges("supervisor", route, ["member", END])
    graph.add_edge("member", "supervisor")
    state = await graph.compile().ainvoke(
        {"round": 0, "next": "", "instruction": "", "contributions": []},
        config={"recursion_limit": crew.max_rounds * 2 + 10},
    )
    contributions: list[Contribution] = state["contributions"]
    if not contributions:
        return "The crew finished without any contribution."

    # Synthesis: the supervisor turns the team's work into one answer for the user.
    await ctx.emitter.checkpoint()
    if crew.supervisor.provider == "mock":
        return "## Team result\n\n" + "\n\n".join(f"### {c['role']} ({c['agent']})\n{c['output']}" for c in contributions)
    message = await stream_model(supervisor_model, [
        SystemMessage(content="You are the crew supervisor. Merge the team's work into a single, coherent final answer for the user. Keep sources and code blocks."),
        HumanMessage(content=f"Goal: {crew.goal}\nUser request: {user_input}\n\nTeam work:\n{_work_so_far(contributions, 6000)}"),
    ], ctx, SUPERVISOR)
    return str(message.content)
