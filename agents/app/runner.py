"""Entry point for executing a RunSpec: picks the right graph and reports the outcome."""
from __future__ import annotations

import asyncio
import logging
import time

import httpx

from app.config import Settings
from app.graphs.agent_graph import run_agent
from app.graphs.crew_graph import run_crew
from app.graphs.prompt_graph import run_prompt
from app.rag.embeddings import Embedder
from app.rag.store import VectorStore
from app.runtime.cancellation import CancellationRegistry
from app.runtime.context import RunContext
from app.runtime.events import EventEmitter, RunCancelled
from app.schemas import RunResult, RunSpec, Usage

log = logging.getLogger(__name__)


async def execute_run(
    spec: RunSpec,
    settings: Settings,
    store: VectorStore,
    embedder: Embedder,
    cancellations: CancellationRegistry,
    http: httpx.AsyncClient | None,
) -> tuple[RunResult, EventEmitter]:
    emitter = EventEmitter(run_id=spec.run_id, callback=spec.callback, cancellations=cancellations, client=http)
    ctx = RunContext(settings=settings, store=store, embedder=embedder, emitter=emitter, owner_id=spec.owner_id)
    started = time.monotonic()
    actor = {"prompt": "LLM", "agent": spec.agent.name if spec.agent else "Agent", "crew": "Supervisor"}[spec.kind]

    async def dispatch() -> str:
        if spec.kind == "prompt":
            if spec.prompt is None:
                raise ValueError("prompt spec missing")
            return await run_prompt(spec.prompt, spec.input, spec.history, ctx)
        if spec.kind == "agent":
            if spec.agent is None:
                raise ValueError("agent spec missing")
            return await run_agent(spec.agent, spec.input, spec.history, ctx)
        if spec.crew is None:
            raise ValueError("crew spec missing")
        return await run_crew(spec.crew, spec.input, ctx)

    try:
        output = await asyncio.wait_for(dispatch(), timeout=settings.max_run_seconds)
    except RunCancelled:
        await emitter.emit("error", "system", "Run cancelled by the user.")
        return RunResult(output="", usage=Usage(), cancelled=True), emitter
    except asyncio.TimeoutError:
        await emitter.emit("error", "system", f"Run exceeded {settings.max_run_seconds}s and was stopped.")
        raise
    except Exception as exc:
        await emitter.emit("error", "system", f"{type(exc).__name__}: {exc}"[:2000])
        raise

    await emitter.emit("final", actor, output, {"duration_ms": int((time.monotonic() - started) * 1000)})
    log.info("run finished", extra={"run_id": spec.run_id, "kind": spec.kind, "tokens_out": ctx.usage.output_tokens})
    return RunResult(
        output=output,
        usage=Usage(input_tokens=ctx.usage.input_tokens, output_tokens=ctx.usage.output_tokens),
        tools_used=sorted(set(ctx.tools_used)),
    ), emitter
