# syntax=docker/dockerfile:1.7
FROM python:3.12-slim AS base

ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 PIP_NO_CACHE_DIR=1 PIP_DISABLE_PIP_VERSION_CHECK=1
WORKDIR /srv

COPY requirements.txt .
RUN pip install -r requirements.txt

COPY app ./app
RUN useradd --system --uid 10001 agents && mkdir -p /data/storage && chown agents /srv
USER agents

EXPOSE 8001
# Two workers; cancellation flags are shared through Redis so any worker can stop any run.
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001", "--workers", "2", "--proxy-headers", "--no-access-log"]
