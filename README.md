<div align="center">

# ZYNTARA

### Agentic AI Studio — from LLM to Agentic AI, one rung at a time

![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-5.8-3178C6?logo=typescript&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.4_LTS-000000?logo=symfony&logoColor=white)
![API Platform](https://img.shields.io/badge/API_Platform-4-38A9B4)
![LangGraph](https://img.shields.io/badge/LangGraph-1.2-1C3C3C)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16_+_pgvector-4169E1?logo=postgresql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7.4-DC382D?logo=redis&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

</div>

---

## Table of Contents

- [Overview](#overview)
- [Project Objectives](#project-objectives)
- [Features](#features)
- [Technologies Used](#technologies-used)
- [UI/UX Design](#uiux-design)
- [Project Architecture](#project-architecture)
- [Installation](#installation)
- [Getting Started](#getting-started)
- [Project Structure](#project-structure)
- [Configuration](#configuration)
- [Screenshots & Preview](#screenshots--preview)
- [Video Presentation](#video-presentation)
- [Performance & Optimization](#performance--optimization)
- [Responsive Design](#responsive-design)
- [Security](#security)
- [Future Improvements](#future-improvements)
- [Author](#author)
- [License](#license)

---

## Overview

**ZYNTARA** is a full-stack platform for building, running and observing AI applications — from a single prompt all the way to teams of autonomous agents.

It is built on a simple idea: every AI product sits on a rung of a ladder.

```
PROMPT  →  AI APP  →  TOOLS  →  RAG  →  AI AGENT  →  AGENTIC AI
```

Each rung is a working feature. You can talk to a model directly, wrap it in an application with a purpose, give it tools to act in the world, ground it in your own documents, let it plan and decide on its own, and finally make several agents collaborate on a shared goal. Every execution is streamed step by step, stored, and analysed.

The application is composed of three services — a **React** single-page app, a **Symfony / API Platform** backend, and a **Python / LangGraph** agents service — backed by **PostgreSQL with pgvector**, **Redis** and a **Mercure** realtime hub, all orchestrated with **Docker Compose**.

---

## Project Objectives

- **Make the AI ladder tangible.** Turn each concept — prompt, app, tools, RAG, agent, agentic — into something users can build and use, and show which rung each creation occupies.
- **Stay provider-agnostic.** Run the same agents on OpenAI, Anthropic, Ollama or a built-in offline model, without code changes.
- **Make reasoning observable.** Expose plans, tool calls, retrievals, hand-offs and self-reviews live, and keep a complete trace of every run.
- **Work without API keys.** Let anyone explore every rung end to end with a deterministic offline model.
- **Be production-minded.** Multi-tenant data isolation, asynchronous processing, rate limiting, health checks, containerisation and automated tests.

---

## Features

### The ladder

| Rung | Feature | What it does |
|---|---|---|
| **Prompt** | Playground | Send a raw prompt to any provider and model, with adjustable temperature. |
| **AI App** | Agents | An LLM with a name, a system prompt, a model and a business activity (generic, e-commerce, education, support, email, research). |
| **Tools** | Tool catalog | `send_email`, `web_search`, `database_query`, `calculator`, `current_datetime`. |
| **RAG** | Knowledge bases | Upload documents, which are extracted, chunked, embedded and searched; agents answer only from them and cite their sources. |
| **AI Agent** | Autonomous mode | A LangGraph loop: plan → act with tools → observe → self-review, bounded by a maximum number of tool rounds. Optional long-term memory. |
| **Agentic AI** | Crews | Two to eight agents with distinct roles, coordinated by an LLM supervisor or executed sequentially. |

### Platform

- **Agent builder** — a five-step wizard (Prompt, Model, Tools, Knowledge, Autonomy) with templates and a live preview of the resulting rung.
- **Agent chat** — multi-conversation chat with history, live reasoning timeline and a link to the full run trace.
- **Knowledge management** — drag-and-drop upload (PDF, TXT, Markdown, CSV, HTML, JSON), indexing status, re-indexing, and a *Test retrieval* panel showing the passages an agent would read.
- **Crew builder** — member ordering, roles, strategy and supervisor model, visualised as an animated agent network.
- **Runs** — filterable, paginated history; each run shows its input, step-by-step trace, output, tokens, duration and tools used, with *Stop* and *Run again* actions.
- **Realtime updates** — run steps, streamed tokens, run status, document indexing and notifications pushed over Mercure, with automatic polling fallback.
- **Dashboard** — ladder progress, key counters, recent runs and weekly activity.
- **Analytics** — runs over time, success rate, token usage, average and p95 duration, tool usage, most-used agents and ladder distribution (7, 30 or 90 days).
- **Notifications** — inbox and toasts for finished or failed runs, indexed documents and account changes.
- **Settings** — profile, default provider and model, notification preference and password change.
- **Administration** — user management (roles, activation), platform-wide activity, platform analytics and a system health view (database, queue, agents service, realtime hub, LLM providers).
- **Authentication** — registration, JWT login, roles (`ROLE_USER`, `ROLE_ADMIN`); the first registered account becomes administrator.
- **Demo workspace** — optional seed data with sample agents on every rung, a crew and a knowledge base.

---

## Technologies Used

### Frontend — `frontend/`

| Technology | Purpose |
|---|---|
| React 19 + TypeScript 5.8 | User interface |
| Vite 6 | Dev server and build tool |
| Tailwind CSS 4 | Styling and design tokens |
| Framer Motion 12 | Animations and transitions |
| TanStack Query 5 | Server state, caching and polling |
| React Router 7 | Routing with lazy-loaded pages |
| Zustand 5 | Authentication store |
| Recharts 3 | Charts |
| `@microsoft/fetch-event-source` | Authenticated Mercure subscriptions |
| React Markdown + remark-gfm | Rendering agent output |
| Lucide React, Sonner | Icons and toasts |
| Vitest + Testing Library | Unit tests |

### Backend — `backend/`

| Technology | Purpose |
|---|---|
| PHP 8.2+ / Symfony 7.4 LTS | Application framework |
| API Platform 4 | REST / JSON-LD resources and OpenAPI docs |
| Doctrine ORM 3 + Migrations | Persistence |
| LexikJWTAuthenticationBundle | JWT authentication |
| Symfony Messenger (Redis / Doctrine transports) | Background jobs |
| Symfony Mercure Bundle | Realtime publishing |
| Symfony Rate Limiter, Validator, Serializer, HttpClient | Platform services |
| NelmioCorsBundle, Monolog | CORS and logging |
| PHPUnit 11 | Functional tests |

### Agents service — `agents/`

| Technology | Purpose |
|---|---|
| Python 3.11+ / FastAPI | HTTP service |
| LangGraph 1.2 / LangChain Core | Agent and multi-agent graphs |
| `langchain-openai`, `langchain-anthropic`, `langchain-ollama` | LLM providers and embeddings |
| psycopg 3 + pgvector | Vector storage |
| Redis | Shared cancellation flags |
| pypdf, BeautifulSoup | Document text extraction |
| pytest + pytest-asyncio | Tests |

### Infrastructure

| Technology | Purpose |
|---|---|
| PostgreSQL 16 + pgvector | Relational data, embeddings, demo business data |
| Redis 7.4 | Message queues, cache, cancellation flags |
| Mercure | Server-sent events hub |
| FrankenPHP | PHP application server |
| nginx | Static hosting and reverse proxy |
| Mailpit | Local inbox for emails sent by agents |
| Docker Compose | Orchestration |
| GitHub Actions | Continuous integration |

---

## UI/UX Design

The interface follows a deliberate visual identity:

- **Dark canvas with a faint grid** and warm **ember-orange radial glows** (`#FF6A1A`).
- **Glassy, rounded tiles** with subtle inner highlights for cards and controls.
- A recurring **glowing "chip" icon** representing the AI, used in empty states, previews and chat.
- **Heavy rounded display type** (Poppins) paired with Inter for body text and JetBrains Mono for code.
- **Design tokens** (colours, fonts, shadows) declared once in `src/index.css` with Tailwind 4's `@theme`.

Signature components illustrate each concept:

| Component | Role |
|---|---|
| `Ladder` | Interactive staircase of the six rungs with counts and progress |
| `AppPreview` (agent builder) | macOS-style "AI APP" window showing the chip and the wired tools |
| `CrewNetwork` | Supervisor and members on a dotted network; the active member glows |
| `RunTimeline` | Animated reasoning trace with typed steps and live streamed text |
| `LevelBadge` / `StatusBadge` | Compact rung and status indicators |

Interaction details include spring-based transitions, skeleton loaders, inline form validation from API violations, confirmation dialogs for destructive actions, keyboard support (Enter to send, Escape to close dialogs) and `prefers-reduced-motion` support.

---

## Project Architecture

```
┌────────────┐  JWT / REST   ┌───────────────────────┐   Messenger (Redis)   ┌──────────────┐
│  React SPA │ ────────────▶ │ Symfony 7.4 +          │ ────────────────────▶ │   Symfony    │
│ (nginx)    │               │ API Platform (web)     │                       │   worker     │
└─────┬──────┘               └───────────┬───────────┘                       └──────┬───────┘
      │ SSE (private topics)             │ publish                                  │ HTTP
      ▼                                  ▼                                          ▼
┌────────────┐◀──────────────  ┌───────────────────────┐   step callbacks   ┌──────────────────┐
│ Mercure hub│                 │ PostgreSQL 16          │◀───────────────────│ Agents service   │
└────────────┘                 │ + pgvector             │   (/internal)      │ FastAPI+LangGraph│
                               └───────────────────────┘◀──── vectors ──────└──────────────────┘
                                          ▲                                          │
                               ┌──────────┴────────────┐                             │
                               │ Redis (queues, cache) │◀────── cancellation flags ──┘
                               └───────────────────────┘
```

### Run lifecycle

Every execution — playground prompt, chat message or crew — is a `Run`.

1. The SPA calls `POST /api/runs` (or `POST /api/conversations/{id}/messages`).
2. Symfony validates ownership, applies rate limiting, stores the run as `queued` and dispatches `ExecuteRun`.
3. The worker builds a run specification and calls `POST /v1/runs/execute` on the agents service.
4. LangGraph executes the graph and streams events (`plan`, `thought`, `tool_call`, `tool_result`, `retrieval`, `handoff`, `reflection`, `token`, `final`, `error`) to `POST /internal/runs/{id}/events`.
5. Symfony stores each step and publishes it on a private Mercure topic; the timeline updates live.
6. The worker records output and token usage, marks the run `succeeded` or `failed`, and creates a notification.
7. `POST /api/runs/{id}/cancel` sets a flag that the graph checks between steps.

### Graphs

| Graph | Flow |
|---|---|
| `prompt_graph` | `llm → END` |
| `agent_graph` (app, tools, RAG) | `agent ⇄ tools → END`, with up-front retrieval when knowledge bases are attached |
| `agent_graph` (autonomous) | `plan → agent ⇄ tools → reflect ↺ → END` |
| `crew_graph` | `supervisor → member (runs its own agent graph) → supervisor … → synthesis` |

### Document ingestion

Upload → shared storage volume → `IngestDocument` message → text extraction → chunking (800 characters, 120 overlap) → embeddings → `kb_chunks` table → document marked `indexed` → notification.

A detailed architecture note, including the data model, is available in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Installation

### Prerequisites

**With Docker (recommended)**

- Docker Engine with Docker Compose v2

**Without Docker (local development)**

| Tool | Version |
|---|---|
| PHP | 8.2+ with `pdo_sqlite` (and `pdo_pgsql` for PostgreSQL) |
| Composer | 2.x |
| Python | 3.11+ |
| Node.js | 20.x |
| OpenSSL CLI | For generating JWT keys if PHP cannot |

### Option 1 — Docker Compose

```bash
cp .env.example .env
```

Edit `.env` and replace every `change-me` value. Generate strong secrets with:

```bash
openssl rand -hex 32
```

Then build and start the stack:

```bash
docker compose up -d --build
```

On first start the backend container waits for the database, generates JWT keys, runs migrations, sets up the message transports and, when `SEED_DEMO=1`, loads the demo workspace.

### Option 2 — Local development without Docker

This mode uses SQLite and the Doctrine message transport; realtime falls back to polling.

**Backend**

```bash
cd backend
composer install
```

Create `backend/.env.local`:

```dotenv
DATABASE_URL="sqlite:///%kernel.project_dir%/var/dev.db"
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=async
MESSENGER_RUNS_TRANSPORT_DSN=doctrine://default?queue_name=runs
MERCURE_ENABLED=0
INTERNAL_API_TOKEN=local-internal-token
```

```bash
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:schema:create
php bin/console messenger:setup-transports
php bin/console app:seed-demo
```

> The migration in `backend/migrations/` targets PostgreSQL. With SQLite, use `doctrine:schema:create` as shown above.

**Agents service**

```bash
cd agents
python -m venv .venv
.venv/bin/pip install -r requirements-dev.txt        # Windows: .venv\Scripts\pip
printf 'INTERNAL_API_TOKEN=local-internal-token\nSTORAGE_DIR=../backend/var/storage\n' > .env
```

**Frontend**

```bash
cd frontend
npm install
```

---

## Getting Started

### Running with Docker

| URL | Service |
|---|---|
| http://localhost:8080 | Application |
| http://localhost:8080/api/docs | OpenAPI / Swagger UI |
| http://localhost:8025 | Mailpit inbox (emails sent by agents) |

### Running locally

Start each process in its own terminal:

```bash
# 1. Backend API
cd backend && php -S 127.0.0.1:8000 -t public

# 2. Background worker
cd backend && php bin/console messenger:consume runs async -vv

# 3. Agents service
cd agents && .venv/bin/uvicorn app.main:app --port 8001

# 4. Frontend (proxies /api to port 8000)
cd frontend && npm run dev
```

Open http://localhost:5173.

> Without `DATABASE_URL`, the agents service keeps embeddings in memory; they are lost when it restarts.

### Demo accounts

Created by `app:seed-demo` (or `SEED_DEMO=1`) on an empty database:

| Email | Password | Role |
|---|---|---|
| `demo@zyntara.local` | `Demo#2026` | User with sample agents, a crew and a knowledge base |
| `admin@zyntara.local` | `Admin#2026` | Administrator |

Change or remove these accounts before exposing the application. To promote any account:

```bash
php bin/console app:create-admin <email> [password] [name]
```

### A first walk up the ladder

1. **Playground** — send a prompt with the *ZYNTARA Mock (offline)* provider.
2. **Agents → New agent** — apply the *Email assistant* template and save it.
3. **Knowledge** — upload a document, wait for *indexed*, then try *Test retrieval*.
4. **Edit an agent** — attach the knowledge base, then enable *Autonomous agent*.
5. **Crews** — open *Launch brief crew* and run it; watch the supervisor hand off to each member.
6. **Runs / Analytics** — inspect the full trace and the metrics.

### Running the tests

```bash
cd backend  && php bin/phpunit
cd agents   && python -m pytest
cd frontend && npx vitest run && npm run build
```

| Suite | Coverage |
|---|---|
| Backend (PHPUnit) | Authentication, profile, admin, tenant isolation, agents and crews, run lifecycle, internal callbacks, chat, uploads, retrieval, dashboard and analytics |
| Agents (pytest) | Every rung, memory, unconfigured providers, cancellation, both crew strategies, tools, chunking, retrieval and the HTTP API |
| Frontend (Vitest) | Ladder, badges, API client, collections, formatting |

---

## Project Structure

```
zyntara/
├── backend/                     # Symfony 7.4 + API Platform
│   ├── config/                  # Bundles, security, messenger, rate limiter, routes
│   ├── docker/                  # Caddyfile, php.ini, entrypoint
│   ├── migrations/              # PostgreSQL schema
│   ├── src/
│   │   ├── ApiPlatform/         # Tenant extension and state processors
│   │   ├── Command/             # app:create-admin, app:seed-demo
│   │   ├── Controller/          # Auth, chat, knowledge, runs, dashboard, admin, internal
│   │   ├── DataFixtures/        # Demo fixtures (dev/test)
│   │   ├── Dto/                 # Validated request payloads
│   │   ├── Entity/              # User, Agent, KnowledgeBase, Document, Crew, Run, …
│   │   ├── Enum/                # Run status, step types, ladder levels, …
│   │   ├── Message/             # ExecuteRun, IngestDocument, DeleteVectors
│   │   ├── MessageHandler/      # Asynchronous handlers
│   │   ├── Repository/
│   │   ├── Security/            # User checker, login listener
│   │   ├── Service/             # Run manager, agents client, realtime, analytics, storage, …
│   │   └── Validator/           # OwnedByCurrentUser constraint
│   ├── tests/                   # PHPUnit functional tests
│   └── Dockerfile
├── agents/                      # Python LangGraph service
│   ├── app/
│   │   ├── graphs/              # prompt, agent and crew graphs
│   │   ├── llm/                 # Provider factory and offline mock model
│   │   ├── rag/                 # Extraction, chunking, embeddings, vector store
│   │   ├── runtime/             # Event emitter, cancellation, run context
│   │   ├── tools/               # Built-in tools and per-agent registry
│   │   ├── config.py · schemas.py · runner.py · main.py
│   ├── tests/
│   ├── requirements.txt · requirements-dev.txt · pyproject.toml
│   └── Dockerfile
├── frontend/                    # React + Vite
│   ├── docker/nginx.conf
│   ├── public/
│   ├── src/
│   │   ├── components/          # UI kit, AppShell, Ladder, RunTimeline, CrewNetwork, …
│   │   ├── lib/                 # API client, queries, realtime, types, formatting
│   │   ├── pages/               # Dashboard, Playground, agents, knowledge, crews, runs, admin, …
│   │   ├── stores/              # Authentication store
│   │   └── test/
│   └── Dockerfile
├── docker/postgres/init/        # pgvector, demo business tables, read-only role
├── docs/ARCHITECTURE.md
├── .github/workflows/ci.yml
├── docker-compose.yml
└── .env.example
```

---

## Configuration

### Root `.env` (Docker Compose)

| Variable | Description | Default |
|---|---|---|
| `HTTP_PORT` | Public port of the application | `8080` |
| `PUBLIC_URL` | Public URL (CORS for Mercure, outgoing User-Agent) | `http://localhost:8080` |
| `APP_SECRET` | Symfony secret | — required |
| `JWT_PASSPHRASE` | Passphrase of the JWT private key | — required |
| `JWT_TTL` | Token lifetime in seconds | `86400` |
| `MERCURE_JWT_SECRET` | Shared secret between backend and Mercure hub | — required |
| `INTERNAL_API_TOKEN` | Shared secret between backend and agents service | — required |
| `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` | Database credentials | `zyntara` / `zyntara` / required |
| `BUSINESS_DB_PASSWORD` | Password of the read-only `zyntara_readonly` role | — required |
| `SEED_DEMO` | Load the demo workspace on an empty database | `1` |
| `WORKER_REPLICAS` | Number of Messenger workers | `2` |

### LLM providers and embeddings

| Variable | Description |
|---|---|
| `OPENAI_API_KEY` / `OPENAI_BASE_URL` | OpenAI or a compatible API |
| `ANTHROPIC_API_KEY` | Anthropic Claude |
| `OLLAMA_BASE_URL` | Local Ollama server, e.g. `http://host.docker.internal:11434` |
| `EMBEDDING_PROVIDER` | `auto`, `openai`, `ollama` or `hash` (local, keyless) |

All providers are optional: the **mock** provider is always available. With `auto`, embeddings use OpenAI when a key is present and the local hashing embedder otherwise.

### Tools

| Variable | Description |
|---|---|
| `TAVILY_API_KEY` | Tavily web search; without it, `web_search` uses the Wikipedia API |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASSWORD` / `SMTP_FROM` / `SMTP_STARTTLS` | Outgoing email (defaults to the bundled Mailpit) |

### Agents service guard-rails (`agents/app/config.py`)

| Setting | Default |
|---|---|
| `MAX_RUN_SECONDS` | `600` |
| `TOOL_TIMEOUT_SECONDS` | `30` |
| `RAG_TOP_K` | `5` |
| `CHUNK_SIZE` / `CHUNK_OVERLAP` | `800` / `120` |
| `EMAIL_MAX_RECIPIENTS` | `5` |
| `HTTP_USER_AGENT` | `ZyntaraAgent/1.0 (+http://localhost:8080)` |

Backend defaults live in `backend/.env`; override them in `backend/.env.local` (never commit secrets). Rate limits are defined in `backend/config/packages/rate_limiter.yaml`: 30 runs per minute per user and 10 registrations per hour per IP, plus 5 login attempts per minute.

---

## Screenshots & Preview

No screenshots are included in the repository yet.

To add them, place images in a folder such as `docs/screenshots/` and reference them here:

```markdown
![Dashboard](docs/screenshots/dashboard.png)
```

Suggested views: Dashboard (ladder), Agent builder, Agent chat with reasoning trace, Knowledge base with *Test retrieval*, Crew run on the agent network, Analytics, Admin system health.

---

## Video Presentation

ZYNTARA is inspired by the short explainer video **"AI in a Minute: From LLM to Agentic AI"**, which walks through the same ladder of concepts:

https://www.tiktok.com/@ibstawi/video/7699179850120056085

The mapping between each scene of the video and the corresponding feature is documented in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md#1-video-analysis--product-features). A presentation video of ZYNTARA itself is not included yet.

---

## Performance & Optimization

**Frontend**

- Every page is lazy-loaded with `React.lazy`; vendor code is split into `react`, `charts` and `motion` chunks.
- TanStack Query caching with a 15-second stale time, no refetch on focus, and polling only while a run or document is in progress.
- nginx serves hashed assets with one-year immutable caching and gzip compression.

**Backend**

- Long-running work (LLM runs, document ingestion, vector deletion) is processed asynchronously by Messenger workers; runs and ingestion use separate transports.
- OPcache with timestamp validation disabled and APCu in the production image; Doctrine query and result caches in production; Redis application cache.
- Provider availability is cached for 60 seconds.
- Analytics are aggregated over a bounded window (up to 20,000 runs).

**Agents service**

- Streamed tokens are buffered and delivered in the background, so a slow callback never slows the model.
- Parallel tool calls, a per-tool timeout and a global run timeout.
- Embeddings are generated in batches of 64; Redis-backed cancellation lets any worker stop any run.

---

## Responsive Design

- **Mobile-first layouts** built with Tailwind breakpoints (`sm`, `md`, `lg`, `xl`).
- Below the `lg` breakpoint the sidebar becomes a **slide-in drawer** with an animated overlay and a sticky top bar.
- Card grids collapse from three columns to one; sidebars of the builder, chat and detail pages stack under the main content.
- Data tables scroll horizontally on small screens; grid tracks are allowed to shrink so long code, URLs and tool output never overflow the page.
- Chat, the agent builder and run timelines remain fully usable on phone-sized viewports.

---

## Security

| Area | Measure |
|---|---|
| Authentication | Stateless JWT (RS256 key pair, configurable TTL); deactivated accounts are blocked on login and on every request |
| Authorization | `ROLE_USER` / `ROLE_ADMIN`; `/api/admin` restricted to administrators; administrators cannot demote or deactivate themselves |
| Multi-tenancy | Every API Platform query is filtered by owner, so foreign resources return 404; references to other users' agents or knowledge bases are rejected |
| Realtime | Private Mercure updates; short-lived subscriber tokens limited to the user's own topics |
| Service-to-service | `/internal/*` protected by a shared secret compared in constant time and not exposed by the public nginx; the agents service also requires the secret |
| Database tool | Dedicated read-only PostgreSQL role limited to the `demo_*` tables, read-only transaction, 5-second timeout, 50-row cap, single `SELECT` only |
| Other tools | Calculator uses a whitelisted AST evaluator (no `eval`); email recipients validated and capped; tool errors are contained |
| Uploads | Extension and content checks, 20 MB limit, randomised file names, paths confined to the storage volume |
| Abuse protection | Rate limits on login, registration and run creation |
| Validation | Symfony Validator on entities and request DTOs |
| HTTP | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy` headers; CORS restricted by configuration |
| Containers | Agents service runs as a non-root user; secrets are supplied through environment variables |

---

## Future Improvements

- Approximate nearest-neighbour indexes (HNSW) for very large knowledge bases.
- Migrating API filters from `#[ApiFilter]` to `#[QueryParameter]`, as API Platform 4.4 deprecates the former.
- Internationalisation, so the interface can be offered in additional languages.
- Light theme (a `theme` preference already exists in the user model).
- More tools, such as authenticated HTTP integrations.
- End-to-end browser tests in CI.

---

## Author

**Dhia Saied**

---

## License

This project is **proprietary**, as declared in `backend/composer.json`. No open-source license file is included; all rights are reserved by the author.
