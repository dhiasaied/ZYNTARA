export type Level = 'prompt' | 'app' | 'tools' | 'rag' | 'agent' | 'agentic'
export type Provider = 'mock' | 'openai' | 'anthropic' | 'ollama'
export type RunStatus = 'queued' | 'running' | 'succeeded' | 'failed' | 'cancelled'
export type RunKind = 'prompt' | 'agent' | 'crew'
export type StepType =
  | 'plan' | 'thought' | 'tool_call' | 'tool_result' | 'retrieval' | 'handoff' | 'reflection' | 'token' | 'final' | 'error'
export type DocumentStatus = 'pending' | 'processing' | 'indexed' | 'failed'

export interface User {
  id: string
  email: string
  fullName: string
  roles: string[]
  isAdmin: boolean
  active: boolean
  preferences: { defaultProvider: Provider; defaultModel: string; toastNotifications: boolean; theme: string }
  createdAt: string
  lastLoginAt: string | null
}

export interface KnowledgeBaseRef { '@id': string; id: string; name: string }

export interface Agent {
  '@id': string
  id: string
  name: string
  description: string | null
  vertical: string
  systemPrompt: string
  provider: Provider
  model: string
  temperature: number
  tools: string[]
  knowledgeBases: KnowledgeBaseRef[]
  autonomous: boolean
  maxIterations: number
  memoryEnabled: boolean
  level: Level
  createdAt: string
  updatedAt: string
}

export interface CrewMember {
  id?: string
  role: string
  position: number
  agent: Pick<Agent, '@id' | 'id' | 'name' | 'level' | 'tools' | 'provider' | 'model' | 'vertical' | 'autonomous'>
}

export interface Crew {
  '@id': string
  id: string
  name: string
  description: string | null
  goal: string
  strategy: 'supervisor' | 'sequential'
  supervisorProvider: Provider
  supervisorModel: string
  maxRounds: number
  members: CrewMember[]
  level: 'agentic'
  createdAt: string
  updatedAt: string
}

export interface KnowledgeBase {
  '@id': string
  id: string
  name: string
  description: string | null
  documentCount: number
  indexedCount: number
  chunkCount: number
  totalBytes: number
  createdAt: string
}

export interface DocumentItem {
  '@id'?: string
  id: string
  originalName: string
  mimeType: string
  size: number
  status: DocumentStatus
  chunkCount: number
  error?: string | null
  createdAt: string
  indexedAt?: string | null
}

export interface RunStep {
  id: string
  sequence: number
  actor: string
  type: StepType
  content: string
  metadata: Record<string, unknown>
  createdAt: string
}

export interface Run {
  '@id': string
  id: string
  kind: RunKind
  title: string
  agent?: { '@id': string; id: string; name: string; level: Level } | null
  crew?: { '@id': string; id: string; name: string } | null
  conversation?: { '@id': string; id: string } | null
  input: string
  provider: Provider | null
  model: string | null
  temperature: number | null
  status: RunStatus
  output: string | null
  error: string | null
  tokensIn: number
  tokensOut: number
  durationMs: number | null
  toolsUsed: string[]
  stepCount: number
  steps?: RunStep[]
  createdAt: string
  startedAt: string | null
  finishedAt: string | null
}

export interface Message {
  id: string
  role: 'user' | 'assistant'
  content: string
  runId: string | null
  runStatus: RunStatus | null
  createdAt: string
}

export interface Conversation {
  '@id': string
  id: string
  title: string
  agent: { '@id': string; id: string; name: string }
  messageCount: number
  lastMessagePreview: string | null
  messages?: Message[]
  createdAt: string
  updatedAt: string
}

export interface Notification {
  '@id'?: string
  id: string
  type: 'run_succeeded' | 'run_failed' | 'document_indexed' | 'document_failed' | 'system'
  title: string
  body: string | null
  link: string | null
  read: boolean
  createdAt: string
}

export interface ToolInfo { key: string; name: string; description: string; icon: string; category: string }
export interface ProviderInfo { id: Provider; name: string; available: boolean; models: string[]; default_model: string; description?: string }
export interface Template {
  id: string
  name: string
  level: Level
  vertical: string
  description: string
  systemPrompt: string
  tools: string[]
  autonomous: boolean
}

export interface Collection<T> { member: T[]; totalItems: number }

export interface LadderCount { level: Level; count: number }
export interface SeriesPoint { date: string; runs: number; succeeded: number; failed: number; tokens: number }

export interface Analytics {
  days: number
  totals: { runs: number; succeeded: number; failed: number; successRate: number | null; tokensIn: number; tokensOut: number; avgDurationMs: number | null; p95DurationMs: number | null }
  byStatus: Record<RunStatus, number>
  byKind: Record<RunKind, number>
  series: SeriesPoint[]
  tools: { tool: string; count: number }[]
  topAgents: { id: string; name: string; runs: number }[]
  ladder: LadderCount[]
  users?: number
}

export interface Dashboard {
  counts: { agents: number; crews: number; knowledgeBases: number; documents: number; runs: number; activeRuns: number; unreadNotifications: number }
  ladder: LadderCount[]
  recentRuns: { id: string; title: string; kind: RunKind; status: RunStatus; input: string; durationMs: number | null; createdAt: string }[]
  week: SeriesPoint[]
}
