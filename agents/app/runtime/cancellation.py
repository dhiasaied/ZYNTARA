"""Cancellation flags shared across service replicas through Redis (in-process fallback)."""
from __future__ import annotations

import logging

log = logging.getLogger(__name__)

TTL_SECONDS = 3600


class CancellationRegistry:
    def __init__(self, redis_url: str = "") -> None:
        self._local: set[str] = set()
        self._redis = None
        if redis_url:
            try:
                from redis import asyncio as aioredis

                self._redis = aioredis.from_url(redis_url, decode_responses=True)
            except Exception as exc:  # noqa: BLE001
                log.warning("Redis unavailable for cancellations, using in-process registry: %s", exc)

    async def cancel(self, run_id: str) -> None:
        self._local.add(run_id)
        if self._redis is not None:
            try:
                await self._redis.set(f"zyntara:cancel:{run_id}", "1", ex=TTL_SECONDS)
            except Exception as exc:  # noqa: BLE001
                log.warning("Could not store cancellation in Redis: %s", exc)

    async def is_cancelled(self, run_id: str) -> bool:
        if run_id in self._local:
            return True
        if self._redis is not None:
            try:
                return bool(await self._redis.exists(f"zyntara:cancel:{run_id}"))
            except Exception:  # noqa: BLE001
                return False
        return False

    async def close(self) -> None:
        if self._redis is not None:
            await self._redis.aclose()
