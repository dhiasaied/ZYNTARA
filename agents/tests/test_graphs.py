import pytest

from app.graphs.agent_graph import run_agent
from app.graphs.crew_graph import run_crew
from app.graphs.prompt_graph import run_prompt
from app.llm.providers import ProviderNotConfigured
from app.runtime.events import RunCancelled
from app.schemas import AgentSpec, CrewMemberSpec, CrewSpec, HistoryMessage, PromptSpec
from tests.conftest import event_types


def agent(**kw) -> AgentSpec:
    base = {"id": "00000000-0000-0000-0000-000000000001", "name": "Helper", "system_prompt": "You are helpful."}
    return AgentSpec(**{**base, **kw})


async def test_prompt_rung_streams_tokens(ctx):
    out = await run_prompt(PromptSpec(), "What is an LLM?", [HistoryMessage(role="user", content="hi"), HistoryMessage(role="assistant", content="hello")], ctx)
    assert "What is an LLM?" in out
    assert ctx.usage.output_tokens > 0


async def test_app_rung_answers_without_tools(ctx):
    out = await run_agent(agent(), "Write a greeting", [], ctx)
    assert "Write a greeting" in out
    assert "tool_call" not in event_types(ctx)


async def test_tools_rung_calls_a_tool(ctx):
    out = await run_agent(agent(tools=["calculator"]), "What is 1200 * 0.15 + 49.9 ?", [], ctx)
    types = event_types(ctx)
    assert types.index("tool_call") < types.index("tool_result")
    assert "229.9" in out
    assert ctx.tools_used == ["calculator"]


async def test_rag_rung_grounds_answer_in_documents(ctx):
    chunks = ["Refunds: products can be returned within 30 days of delivery.", "Shipping takes 3 to 5 business days."]
    await ctx.store.upsert_document("kb-1", "doc-1", "policies.md", chunks, await ctx.embedder.embed(chunks), ctx.embedder.model_name)
    out = await run_agent(agent(knowledge_base_ids=["kb-1"]), "Within how many days can products be returned?", [], ctx)
    retrieval = next(e for e in ctx.emitter.events if e["type"] == "retrieval")
    assert retrieval["metadata"]["sources"][0]["filename"] == "policies.md"
    assert "30 days" in out


async def test_agent_rung_plans_acts_and_reflects(ctx):
    out = await run_agent(agent(tools=["calculator", "current_datetime"], autonomous=True, max_iterations=4), "Compute 17 * 23 and tell me today's date", [], ctx)
    types = event_types(ctx)
    assert types[0] == "plan"
    assert "tool_call" in types and "reflection" in types
    assert "391" in out


async def test_memory_is_saved_and_recalled(ctx):
    spec = agent(memory_enabled=True)
    await run_agent(spec, "Remember that my favourite language is Python", [], ctx)
    assert ctx.store.memories and "Python" in ctx.store.memories[0]["content"]
    out = await run_agent(spec, "Do you remember my favourite language?", [], ctx)
    assert "Python" in out


async def test_unconfigured_provider_is_reported(ctx):
    with pytest.raises(ProviderNotConfigured):
        await run_agent(agent(provider="openai", model="gpt-5-mini"), "hi", [], ctx)


async def test_cancellation_stops_the_graph(ctx):
    await ctx.emitter.cancellations.cancel("run-1")
    with pytest.raises(RunCancelled):
        await run_agent(agent(), "hi", [], ctx)


@pytest.mark.parametrize("strategy", ["supervisor", "sequential"])
async def test_agentic_rung_crew_collaborates(ctx, strategy):
    crew = CrewSpec(
        id="crew-1", name="Brief crew", goal="Write a reviewed brief", strategy=strategy,
        members=[
            CrewMemberSpec(role="Researcher", agent=agent(id="00000000-0000-0000-0000-00000000000a", name="Research agent", tools=["calculator"])),
            CrewMemberSpec(role="Reviewer", agent=agent(id="00000000-0000-0000-0000-00000000000b", name="Reviewer")),
        ],
    )
    out = await run_crew(crew, "Brief on 2 + 2", ctx)
    handoffs = [e for e in ctx.emitter.events if e["type"] == "handoff"]
    assert [h["metadata"]["to"] for h in handoffs] == ["Researcher", "Reviewer", "FINISH"]
    assert "### Researcher" in out and "### Reviewer" in out
    actors = {e["actor"] for e in ctx.emitter.events}
    assert {"Supervisor", "Research agent", "Reviewer"} <= actors
