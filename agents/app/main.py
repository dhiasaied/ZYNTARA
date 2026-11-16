"""FastAPI surface of the agents service. Only Symfony talks to it (shared secret)."""
from __future__ import annotations

import hmac
import json
import logging
import sys
import time
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Annotated, Any

import httpx
from fastapi import Depends, FastAPI, Header, HTTPException, Request
from fastapi.responses import JSONResponse

from app.config import Settings, get_settings
from app.llm.providers import ProviderNotConfigured, list_providers
from app.rag.embeddings import build_embedder
from app.rag.extract import ExtractionError, chunk_text, extract_text
from app.rag.store import build_store
from app.runner import execute_run
from app.runtime.cancellation import CancellationRegistry
from app.schemas import DeleteVectorsRequest, IngestRequest, RunResult, RunSpec, SearchRequest


class JsonFormatter(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        payload: dict[str, Any] = {"ts": self.formatTime(record), "level": record.levelname, "logger": record.name, "msg": record.getMessage()}
        for key in ("run_id", "kind", "error", "path", "status", "duration_ms", "tokens_out", "document_id"):
            if hasattr(record, key):
                payload[key] = getattr(record, key)
        if record.exc_info:
            payload["exc"] = self.formatException(record.exc_info)
        return json.dumps(payload, ensure_ascii=False)


def configure_logging(level: str) -> None:
    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(JsonFormatter())
    logging.basicConfig(level=level, handlers=[handler], force=True)


log = logging.getLogger("zyntara.agents")


@asynccontextmanager
async def lifespan(app: FastAPI):
    settings = get_settings()
    configure_logging(settings.log_level)
    store = build_store(settings.database_url)
    await store.init()
    app.state.settings = settings
    app.state.store = store
    app.state.embedder = build_embedder(settings)
    app.state.cancellations = CancellationRegistry(settings.redis_url)
    app.state.http = httpx.AsyncClient()
    log.info("agents service ready", extra={"path": str(settings.storage_dir)})
    yield
    await app.state.http.aclose()
    await app.state.cancellations.close()
    await store.close()


app = FastAPI(title="ZYNTARA Agents", version="1.0.0", lifespan=lifespan)


def settings_dep(request: Request) -> Settings:
    return request.app.state.settings


def require_token(request: Request, x_internal_token: Annotated[str, Header()] = "") -> None:
    expected = request.app.state.settings.internal_api_token
    if not expected or not hmac.compare_digest(expected, x_internal_token):
        raise HTTPException(status_code=401, detail="Invalid internal token")


Protected = Depends(require_token)


@app.middleware("http")
async def access_log(request: Request, call_next):
    started = time.monotonic()
    response = await call_next(request)
    log.info("request", extra={"path": request.url.path, "status": response.status_code, "duration_ms": int((time.monotonic() - started) * 1000)})
    return response


@app.exception_handler(ProviderNotConfigured)
async def provider_not_configured(_: Request, exc: ProviderNotConfigured) -> JSONResponse:
    return JSONResponse(status_code=422, content={"detail": str(exc)})


@app.get("/health")
async def health(request: Request) -> dict[str, Any]:
    store_ok = await request.app.state.store.healthy()
    return {
        "status": "ok" if store_ok else "degraded",
        "vector_store": type(request.app.state.store).__name__,
        "vector_store_ok": store_ok,
        "embedder": request.app.state.embedder.model_name,
    }


@app.get("/v1/providers", dependencies=[Protected])
async def providers(settings: Annotated[Settings, Depends(settings_dep)]) -> dict[str, Any]:
    return {"providers": list_providers(settings)}


@app.post("/v1/runs/execute", dependencies=[Protected], response_model=RunResult)
async def execute(spec: RunSpec, request: Request) -> RunResult:
    state = request.app.state
    try:
        result, _ = await execute_run(spec, state.settings, state.store, state.embedder, state.cancellations, state.http)
    except ProviderNotConfigured:
        raise
    except TimeoutError as exc:
        raise HTTPException(status_code=504, detail="The run took too long and was stopped.") from exc
    except Exception as exc:
        log.exception("run failed", extra={"run_id": spec.run_id})
        raise HTTPException(status_code=500, detail=f"{type(exc).__name__}: {exc}"[:1000]) from exc
    return result


@app.post("/v1/runs/{run_id}/cancel", dependencies=[Protected])
async def cancel(run_id: str, request: Request) -> dict[str, Any]:
    await request.app.state.cancellations.cancel(run_id)
    return {"ok": True}


def _resolve_storage_path(settings: Settings, relative: str) -> Path:
    base = settings.storage_dir.resolve()
    target = (base / relative).resolve()
    if base not in target.parents:
        raise HTTPException(status_code=400, detail="Invalid document path")
    return target


@app.post("/v1/ingest", dependencies=[Protected])
async def ingest(body: IngestRequest, request: Request) -> dict[str, Any]:
    state = request.app.state
    path = _resolve_storage_path(state.settings, body.path)
    try:
        text = extract_text(path, body.mime_type)
    except ExtractionError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    chunks = chunk_text(text, state.settings.chunk_size, state.settings.chunk_overlap)
    if not chunks:
        raise HTTPException(status_code=422, detail="The document contains no text.")
    embeddings: list[list[float]] = []
    for i in range(0, len(chunks), 64):
        embeddings += await state.embedder.embed(chunks[i : i + 64])
    count = await state.store.upsert_document(body.knowledge_base_id, body.document_id, body.filename, chunks, embeddings, state.embedder.model_name)
    log.info("document indexed", extra={"document_id": body.document_id, "status": count})
    return {"chunks": count, "embedding_model": state.embedder.model_name}


@app.post("/v1/vectors/delete", dependencies=[Protected])
async def delete_vectors(body: DeleteVectorsRequest, request: Request) -> dict[str, Any]:
    if not body.document_id and not body.knowledge_base_id:
        raise HTTPException(status_code=422, detail="document_id or knowledge_base_id is required")
    deleted = await request.app.state.store.delete(document_id=body.document_id, kb_id=body.knowledge_base_id)
    return {"deleted": deleted}


@app.post("/v1/search", dependencies=[Protected])
async def search(body: SearchRequest, request: Request) -> dict[str, Any]:
    state = request.app.state
    [embedding] = await state.embedder.embed([body.query])
    results = await state.store.search(body.knowledge_base_ids, embedding, state.embedder.model_name, body.k)
    return {"results": results, "embedding_model": state.embedder.model_name}
