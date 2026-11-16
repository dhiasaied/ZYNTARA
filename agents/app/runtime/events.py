"""Streams run steps back to Symfony (which stores them and pushes them to the browser)."""
from __future__ import annotations

import asyncio
import logging
import time
from dataclasses import dataclass, field
from typing import Any

import httpx

from app.runtime.cancellation import CancellationRegistry
from app.schemas import Callback

log = logging.getLogger(__name__)


class RunCancelled(Exception):
    """Raised inside a graph when the user cancelled the run."""


@dataclass
class EventEmitter:
    run_id: str
    callback: Callback | None
    cancellations: CancellationRegistry
    client: httpx.AsyncClient | None = None
    token_flush_seconds: float = 0.15
    events: list[dict[str, Any]] = field(default_factory=list)
    _token_buffer: dict[str, str] = field(default_factory=dict)
    _last_flush: float = field(default_factory=time.monotonic)
    _remote_cancelled: bool = False
    _token_task: asyncio.Task[None] | None = None

    async def emit(self, type_: str, actor: str, content: str = "", metadata: dict[str, Any] | None = None) -> None:
        await self.flush_tokens()
        event = {"type": type_, "actor": actor, "content": content, "metadata": metadata or {}}
        self.events.append(event)
        await self._post([event])

    async def token(self, actor: str, delta: str) -> None:
        """Buffers streamed text. Delivery happens in the background so a slow callback
        never slows the model down; at most one token delivery is in flight at a time."""
        if not delta:
            return
        self._token_buffer[actor] = self._token_buffer.get(actor, "") + delta
        in_flight = self._token_task is not None and not self._token_task.done()
        if not in_flight and time.monotonic() - self._last_flush >= self.token_flush_seconds:
            self._token_task = asyncio.create_task(self._post(self._drain_tokens()))

    async def flush_tokens(self) -> None:
        """Waits for in-flight token delivery, then sends what is left (keeps event order)."""
        if self._token_task is not None:
            await self._token_task
            self._token_task = None
        batch = self._drain_tokens()
        if batch:
            await self._post(batch)

    def _drain_tokens(self) -> list[dict[str, Any]]:
        batch = [{"type": "token", "actor": actor, "content": text} for actor, text in self._token_buffer.items()]
        self._token_buffer.clear()
        self._last_flush = time.monotonic()
        return batch

    async def checkpoint(self) -> None:
        """Called between graph steps: stop as soon as the run was cancelled."""
        if self._remote_cancelled or await self.cancellations.is_cancelled(self.run_id):
            raise RunCancelled(self.run_id)

    async def _post(self, events: list[dict[str, Any]]) -> None:
        if self.callback is None or self.client is None:
            return
        try:
            response = await self.client.post(
                self.callback.url,
                json={"events": events},
                headers={"X-Internal-Token": self.callback.token},
                timeout=10,
            )
            if response.status_code < 300 and response.json().get("status") == "cancelled":
                self._remote_cancelled = True
        except (httpx.HTTPError, ValueError) as exc:
            # Losing a progress event must never kill the run itself.
            log.warning("Could not deliver run events", extra={"run_id": self.run_id, "error": str(exc)})


async def gather_with_limit(coros: list[Any], limit: int = 4) -> list[Any]:
    semaphore = asyncio.Semaphore(limit)

    async def run(coro: Any) -> Any:
        async with semaphore:
            return await coro

    return await asyncio.gather(*(run(c) for c in coros))
