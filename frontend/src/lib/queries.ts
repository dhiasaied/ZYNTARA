import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { asCollection, del, get, patch, post } from './api'
import type {
  Agent, Analytics, Conversation, Crew, Dashboard, DocumentItem, KnowledgeBase, Notification, ProviderInfo, Run,
  Template, ToolInfo, User,
} from './types'

const ACTIVE: Run['status'][] = ['queued', 'running']

// ---------------------------------------------------------------- catalog
export const useTools = () => useQuery({ queryKey: ['catalog', 'tools'], queryFn: () => get<ToolInfo[]>('/api/catalog/tools'), staleTime: Infinity })
export const useTemplates = () => useQuery({ queryKey: ['catalog', 'templates'], queryFn: () => get<Template[]>('/api/catalog/templates'), staleTime: Infinity })
export const useProviders = () => useQuery({ queryKey: ['catalog', 'providers'], queryFn: () => get<ProviderInfo[]>('/api/catalog/providers'), staleTime: 60_000 })

// ---------------------------------------------------------------- dashboard / analytics
export const useDashboard = () => useQuery({ queryKey: ['dashboard'], queryFn: () => get<Dashboard>('/api/dashboard') })
export const useAnalytics = (days: number, admin = false) =>
  useQuery({ queryKey: ['analytics', admin, days], queryFn: () => get<Analytics>(admin ? '/api/admin/analytics' : '/api/analytics', { days }) })

// ---------------------------------------------------------------- agents
export const useAgents = (search = '') =>
  useQuery({ queryKey: ['agents', search], queryFn: async () => asCollection<Agent>(await get('/api/agents', { name: search, itemsPerPage: 100 })) })
export const useAgent = (id?: string) =>
  useQuery({ queryKey: ['agent', id], queryFn: () => get<Agent>(`/api/agents/${id}`), enabled: !!id })

export function useSaveAgent() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: Omit<Partial<Agent>, 'knowledgeBases'> & { knowledgeBases?: string[] } }) =>
      id ? patch<Agent>(`/api/agents/${id}`, data) : post<Agent>('/api/agents', data),
    onSuccess: (agent) => {
      qc.invalidateQueries({ queryKey: ['agents'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
      qc.setQueryData(['agent', agent.id], agent)
    },
  })
}

export function useDeleteAgent() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => del(`/api/agents/${id}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['agents'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })
}

// ---------------------------------------------------------------- crews
export const useCrews = () => useQuery({ queryKey: ['crews'], queryFn: async () => asCollection<Crew>(await get('/api/crews', { itemsPerPage: 100 })) })
export const useCrew = (id?: string) => useQuery({ queryKey: ['crew', id], queryFn: () => get<Crew>(`/api/crews/${id}`), enabled: !!id })

export function useSaveCrew() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: Record<string, unknown> }) => (id ? patch<Crew>(`/api/crews/${id}`, data) : post<Crew>('/api/crews', data)),
    onSuccess: (crew) => {
      qc.invalidateQueries({ queryKey: ['crews'] })
      qc.setQueryData(['crew', crew.id], crew)
    },
  })
}

export function useDeleteCrew() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (id: string) => del(`/api/crews/${id}`), onSuccess: () => qc.invalidateQueries({ queryKey: ['crews'] }) })
}

// ---------------------------------------------------------------- knowledge
export const useKnowledgeBases = () =>
  useQuery({ queryKey: ['kbs'], queryFn: async () => asCollection<KnowledgeBase>(await get('/api/knowledge_bases', { itemsPerPage: 100 })) })
export const useKnowledgeBase = (id?: string) =>
  useQuery({ queryKey: ['kb', id], queryFn: () => get<KnowledgeBase>(`/api/knowledge_bases/${id}`), enabled: !!id })
export const useDocuments = (kbId?: string) =>
  useQuery({
    queryKey: ['documents', kbId],
    enabled: !!kbId,
    queryFn: async () => asCollection<DocumentItem>(await get('/api/documents', { knowledgeBase: `/api/knowledge_bases/${kbId}`, itemsPerPage: 100 })),
    // Fallback polling while something is being indexed (realtime normally pushes updates).
    refetchInterval: (q) => (q.state.data?.member.some((d) => d.status === 'pending' || d.status === 'processing') ? 4000 : false),
  })

export function useSaveKnowledgeBase() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id?: string; data: { name: string; description?: string | null } }) =>
      id ? patch<KnowledgeBase>(`/api/knowledge_bases/${id}`, data) : post<KnowledgeBase>('/api/knowledge_bases', data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['kbs'] }),
  })
}

// ---------------------------------------------------------------- runs
export const useRuns = (filters: Record<string, string | number | undefined>) =>
  useQuery({
    queryKey: ['runs', filters],
    queryFn: async () => asCollection<Run>(await get('/api/runs', filters)),
    refetchInterval: (q) => (q.state.data?.member.some((r) => ACTIVE.includes(r.status)) ? 5000 : false),
  })

export const useRun = (id?: string) =>
  useQuery({
    queryKey: ['run', id],
    enabled: !!id,
    queryFn: () => get<Run>(`/api/runs/${id}`),
    refetchInterval: (q) => (q.state.data && ACTIVE.includes(q.state.data.status) ? 4000 : false),
  })

export function useCreateRun() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Record<string, unknown>) => post<Run>('/api/runs', data),
    onSuccess: (run) => {
      qc.setQueryData(['run', run.id], { ...run, steps: [] })
      qc.invalidateQueries({ queryKey: ['runs'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })
}

// ---------------------------------------------------------------- conversations
export const useConversations = (agentId?: string) =>
  useQuery({
    queryKey: ['conversations', agentId],
    enabled: !!agentId,
    queryFn: async () => asCollection<Conversation>(await get('/api/conversations', { agent: `/api/agents/${agentId}` })),
  })
export const useConversation = (id?: string) =>
  useQuery({ queryKey: ['conversation', id], enabled: !!id, queryFn: () => get<Conversation>(`/api/conversations/${id}`) })

// ---------------------------------------------------------------- notifications
export const useNotifications = (unreadOnly = false) =>
  useQuery({
    queryKey: ['notifications', unreadOnly],
    queryFn: async () => asCollection<Notification>(await get('/api/notifications', unreadOnly ? { 'exists[readAt]': false } : {})),
  })
export const useUnreadCount = () =>
  useQuery({ queryKey: ['notifications', 'unread'], queryFn: () => get<{ count: number }>('/api/notifications/unread-count'), refetchInterval: 60_000 })

export function useMarkNotification() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, read }: { id: string; read: boolean }) => patch(`/api/notifications/${id}`, { read }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  })
}

export function useReadAll() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: () => post('/api/notifications/read-all'), onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }) })
}

// ---------------------------------------------------------------- me
export const useMe = (enabled: boolean) => useQuery({ queryKey: ['me'], queryFn: () => get<User>('/api/me'), enabled })
