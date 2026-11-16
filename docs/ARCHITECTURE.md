# ZYNTARA — Architecture

> ZYNTARA is built directly on the reference video ("AI in a Minute: From LLM to Agentic AI"),
> which climbs a ladder of concepts:
>
> `LLM → PROMPT → AI APP → TOOLS → RAG → AI AGENT → AGENTIC AI`
>
> Every rung shown in the video is a working feature here.

## 1. Video analysis → product features

| # | Scene in the video (timestamp) | What it teaches | Feature in ZYNTARA |
|---|---|---|---|
| 1 | 0–6s: chip icon in an orbit, "billions of data" | An **LLM** is a model trained on huge data that answers | Provider-agnostic LLM layer (OpenAI, Anthropic, Ollama, built-in offline `mock`) |
| 2 | 6–12s: chip with eye / pen / chat satellites, "and it answers" | Ask → it replies | **Playground** — raw prompt to any model (`kind=prompt` run) |
| 3 | 14–18s: big "Prompt" word, `PROMPT → LLM` | The **prompt** is what you send | System prompt + user prompt editor in the agent builder |
| 4 | 22s: "AI APP" macOS window around the chip, "Email" | Wrap the LLM in an **application** with a purpose | **Agents** with a name, system prompt, model, vertical (level `APP`) |
| 5 | 26s: "Tools" with email / web / database icons wired under the app | Give the app **tools** | Tool catalog: `send_email`, `web_search`, `database_query`, `calculator`, `current_datetime` (level `TOOLS`) |
| 6 | 30–34s: document card flies into the app, "only from them" | **RAG**: answer only from your documents | **Knowledge bases**: upload PDF/TXT/MD/CSV/HTML → chunk → embed (pgvector) → `knowledge_search` tool, grounded answers with citations (level `RAG`) |
| 7 | 38s: "the next step" — AI core in an orbit of plan / branch / bolt / pen | An **AI Agent** decides on its own in a loop | **Autonomous mode**: LangGraph `plan → act ↔ tools → reflect` loop with max iterations + long-term memory (level `AGENT`) |
| 8 | 46–54s: four robot nodes on a dotted network, each with a badge (search / review / code) | **Agentic AI**: several agents collaborating | **Crews**: multi-agent orchestration (supervisor or sequential) with role-specialised agents (level `AGENTIC`) |
| 9 | 58–66s: staircase `PROMPT APP TOOLS RAG AGENT AGENTIC`, shop + education icons | Recap; applies to any business activity | **Ladder dashboard** showing which rung each agent sits on; **templates** per vertical (e-commerce, education, support, email) |

Visual identity extracted from the video: near-black background with a faint grid,
warm orange radial glow (`#FF6A1A`), glassy rounded-square tiles, a glowing chip
icon, macOS-style "AI APP" window chrome, orbit rings, dotted connector lines,
heavy rounded display type. The frontend uses exactly that language.

Nothing outside the video's scope was added beyond what a real product needs to
run (auth, roles, settings, notifications, analytics, admin).

## 2. System overview

```
┌────────────┐  JWT/REST   ┌──────────────────────┐  Messenger (Redis)  ┌──────────────┐
│  React SPA │ ──────────▶ │ Symfony 7.4 + API    │ ──────────────────▶ │ Symfony      │
│ (Vite, TS) │             │ Platform 4 (web)     │                     │ worker       │
└─────┬──────┘             └──────────┬───────────┘                     └──────┬───────┘
      │ SSE (private)                 │ publish                                │ HTTP
      ▼                               ▼                                        ▼
┌────────────┐◀──────────── ┌──────────────────────┐  step callbacks   ┌──────────────────┐
│ Mercure hub│              │ PostgreSQL 16        │◀──────────────────│ Agents service   │
└────────────┘              │ + pgvector           │   (internal API)  │ FastAPI+LangGraph│
                            └──────────────────────┘◀──── vectors ─────└──────────────────┘
                                       ▲                                      │
                            ┌──────────┴───────────┐                          │ cancel flags,
                            │ Redis 7 (queue,cache)│◀─────────────────────────┘ provider cache
                            └──────────────────────┘
```

### Run lifecycle (every execution — playground, chat, crew — is a `Run`)

1. SPA `POST /api/runs` (or `POST /api/conversations/{id}/messages`).
2. Symfony validates ownership, rate-limits, stores `Run{status: queued}`, dispatches `ExecuteRun` to Redis.
3. Worker builds a **run spec** (agent/crew config, history, KB ids) and calls `POST /v1/runs/execute` on the agents service.
4. LangGraph executes; each node emits events (`plan`, `thought`, `tool_call`, `tool_result`, `retrieval`, `handoff`, `reflection`, `final`) to `POST /internal/runs/{id}/events` (shared secret).
5. Symfony stores a `RunStep` and publishes it on Mercure topic `…/users/{uid}/runs/{runId}`; the SPA timeline animates it live.
6. Worker stores output/usage, marks the run `succeeded|failed`, writes a `Notification` and publishes it on `…/users/{uid}/notifications`.
7. Cancel: `POST /api/runs/{id}/cancel` sets a Redis flag the graph checks between nodes.

### Document ingestion (RAG)

Upload → file stored on the shared `storage` volume → `IngestDocument` message → agents
service extracts text, chunks (800/120 overlap), embeds and writes to `kb_chunks` (pgvector) →
document marked `indexed` with chunk count → notification.

## 3. Backend (Symfony) modules

```
src/
  Entity/        User, Agent, Crew, CrewMember, KnowledgeBase, Document, Conversation,
                 Message, Run, RunStep, Notification
  Enum/          RunStatus, RunKind, StepType, DocumentStatus, AgentLevel, CrewStrategy
  ApiPlatform/   CurrentUserExtension (row-level ownership), OwnerProcessor, RunCreateProcessor
  Controller/    Auth, Me, Catalog, Conversation, Document upload/search, Run cancel,
                 Realtime token, Dashboard, Analytics, Admin, Internal callbacks
  Message/ + MessageHandler/   ExecuteRun, IngestDocument, DeleteDocumentVectors
  Service/       AgentServiceClient, RunSpecBuilder, RunManager, RunEventRecorder,
                 NotificationService, RealtimePublisher, DocumentStorage, ToolCatalog,
                 TemplateCatalog, AnalyticsService
  Security/      UserChecker, InternalTokenAuthenticator (via controller guard)
```

Roles: `ROLE_USER` (build/run own agents), `ROLE_ADMIN` (users, system health, all runs).

## 4. Agents service (Python)

```
agents/app/
  main.py            FastAPI: /health, /v1/providers, /v1/runs/execute, /v1/ingest,
                     /v1/documents/{id} (DELETE), /v1/search
  llm/providers.py   openai | anthropic | ollama | mock (deterministic offline model)
  graphs/            prompt_graph, agent_graph (plan→act↔tools→reflect), crew_graph (supervisor/sequential)
  tools/             registry + web_search, send_email, database_query, calculator,
                     current_datetime, knowledge_search, save_memory, recall_memory
  rag/               extraction, chunking, embeddings (openai|ollama|hash), pgvector store
  runtime/           event emitter (callbacks), cancellation (Redis)
```

## 5. Data model (PostgreSQL)

`users` 1─* `agents` *─* `knowledge_bases` 1─* `documents`
`users` 1─* `crews` 1─* `crew_members` *─1 `agents`
`users` 1─* `conversations` 1─* `messages`
`users` 1─* `runs` 1─* `run_steps`; `runs` *─1 `agents|crews|conversations`
`users` 1─* `notifications`
`kb_chunks(id, document_id, knowledge_base_id, content, embedding vector, metadata)` — owned by agents service
`agent_memories(id, agent_id, content, embedding vector, created_at)` — owned by agents service
`demo_*` business tables — sandbox for the read-only `database_query` tool.
