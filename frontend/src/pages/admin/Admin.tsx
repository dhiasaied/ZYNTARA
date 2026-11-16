import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import clsx from 'clsx'
import { Activity, BarChart3, CheckCircle2, HeartPulse, RefreshCw, Search, Users, XCircle } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button, Card, Input, PageHeader, Pill, Skeleton, StatusBadge, Tabs } from '../../components/ui'
import { get, patch } from '../../lib/api'
import { duration, timeAgo } from '../../lib/format'
import { useAnalytics } from '../../lib/queries'
import type { RunStatus, User } from '../../lib/types'
import { useAuth } from '../../stores/auth'
import { AnalyticsView } from '../AnalyticsView'

type AdminUser = User & { agents: number; runs: number }
interface HealthCheck { ok: boolean; latency_ms?: number; error?: string; [k: string]: unknown }

function UsersTab() {
  const me = useAuth((s) => s.user)
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'users', q, page],
    queryFn: () => get<{ items: AdminUser[]; total: number; perPage: number }>('/api/admin/users', { q, page }),
  })
  const update = useMutation({
    mutationFn: ({ id, body }: { id: string; body: Record<string, unknown> }) => patch<User>(`/api/admin/users/${id}`, body, 'application/json'),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'users'] }),
  })

  return (
    <Card className="p-0">
      <div className="flex items-center gap-3 border-b border-white/[0.06] p-4">
        <div className="relative max-w-xs flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-sand-500" />
          <Input value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} placeholder="Search by name or email" className="pl-9" aria-label="Search users" />
        </div>
        <span className="ml-auto text-xs text-sand-500">{data?.total ?? 0} users</span>
      </div>
      {isLoading ? <Skeleton className="m-4 h-60" /> : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-sm">
            <thead><tr className="text-left text-xs text-sand-500 uppercase"><th className="px-4 py-2">User</th><th className="px-4 py-2">Role</th><th className="px-4 py-2 text-right">Agents</th><th className="px-4 py-2 text-right">Runs</th><th className="px-4 py-2">Last login</th><th className="px-4 py-2">Status</th><th className="px-4 py-2" /></tr></thead>
            <tbody className="divide-y divide-white/[0.04]">
              {data?.items.map((u) => {
                const self = u.id === me?.id
                return (
                  <tr key={u.id}>
                    <td className="px-4 py-3"><div className="font-semibold">{u.fullName}{self && <span className="ml-1.5 text-xs text-ember-400">(you)</span>}</div><div className="text-xs text-sand-500">{u.email}</div></td>
                    <td className="px-4 py-3">{u.isAdmin ? <Pill className="border-ember-500/30 text-ember-300">Admin</Pill> : <Pill>User</Pill>}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{u.agents}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{u.runs}</td>
                    <td className="px-4 py-3 text-xs text-sand-400">{timeAgo(u.lastLoginAt)}</td>
                    <td className="px-4 py-3">{u.active ? <span className="text-xs text-green-300">Active</span> : <span className="text-xs text-red-300">Deactivated</span>}</td>
                    <td className="px-4 py-3 text-right whitespace-nowrap">
                      <Button size="sm" variant="ghost" disabled={self} onClick={() => update.mutate({ id: u.id, body: { roles: u.isAdmin ? ['ROLE_USER'] : ['ROLE_USER', 'ROLE_ADMIN'] } })}>
                        {u.isAdmin ? 'Revoke admin' : 'Make admin'}
                      </Button>
                      <Button size="sm" variant={u.active ? 'danger' : 'secondary'} disabled={self} onClick={() => update.mutate({ id: u.id, body: { active: !u.active } })}>
                        {u.active ? 'Deactivate' : 'Reactivate'}
                      </Button>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
      {data && data.total > data.perPage && (
        <div className="flex justify-end gap-2 border-t border-white/[0.06] p-3">
          <Button size="sm" variant="ghost" disabled={page === 1} onClick={() => setPage(page - 1)}>Previous</Button>
          <Button size="sm" variant="ghost" disabled={page * data.perPage >= data.total} onClick={() => setPage(page + 1)}>Next</Button>
        </div>
      )}
    </Card>
  )
}

function HealthTab() {
  const { data, isLoading, refetch, isFetching } = useQuery({
    queryKey: ['admin', 'health'],
    queryFn: () => get<{ ok: boolean; checks: Record<string, HealthCheck>; time: string }>('/api/admin/health'),
    refetchInterval: 30_000,
  })
  if (isLoading || !data) return <Skeleton className="h-60" />
  const providers = (data.checks.providers?.items ?? []) as { id: string; name: string; available: boolean }[]
  const services = Object.entries(data.checks).filter(([k]) => k !== 'providers')

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className={clsx('flex items-center gap-2 font-display font-bold', data.ok ? 'text-green-300' : 'text-red-300')}>
          {data.ok ? <CheckCircle2 className="size-5" /> : <XCircle className="size-5" />}{data.ok ? 'All core systems operational' : 'Degraded'}
        </div>
        <Button size="sm" variant="secondary" loading={isFetching} onClick={() => refetch()} icon={<RefreshCw className="size-3.5" />}>Refresh</Button>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {services.map(([name, c]) => (
          <Card key={name} className="p-4">
            <div className="flex items-center justify-between"><span className="font-semibold capitalize">{name}</span><span className={clsx('size-2.5 rounded-full', c.ok ? 'bg-ok-500' : 'bg-bad-500')} /></div>
            <div className="mt-2 text-xs text-sand-400">
              {c.latency_ms !== undefined && <div>{c.latency_ms} ms</div>}
              {c.queued_runs !== undefined && <div>{String(c.queued_runs)} queued runs</div>}
              {c.error && <div className="line-clamp-3 text-red-300">{c.error}</div>}
            </div>
          </Card>
        ))}
      </div>
      <Card>
        <h3 className="mb-3 font-display text-sm font-bold">LLM providers</h3>
        <div className="flex flex-wrap gap-2">
          {providers.map((p) => <Pill key={p.id} className={p.available ? 'border-ok-500/30 text-green-200' : 'text-sand-500'}>{p.available ? '●' : '○'} {p.name}</Pill>)}
        </div>
      </Card>
    </div>
  )
}

function ActivityTab() {
  const [status, setStatus] = useState<RunStatus | ''>('')
  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'runs', status],
    queryFn: () => get<{ id: string; title: string; kind: string; status: RunStatus; owner: string; tokens: number; durationMs: number | null; error: string | null; createdAt: string }[]>('/api/admin/runs', { status }),
    refetchInterval: 15_000,
  })
  return (
    <Card className="p-0">
      <div className="flex gap-2 border-b border-white/[0.06] p-3">
        {(['', 'running', 'failed', 'succeeded'] as const).map((s) => (
          <Button key={s || 'all'} size="sm" variant={status === s ? 'primary' : 'ghost'} onClick={() => setStatus(s)}>{s || 'All'}</Button>
        ))}
      </div>
      {isLoading ? <Skeleton className="m-4 h-60" /> : (
        <ul className="divide-y divide-white/[0.04]">
          {data?.map((r) => (
            <li key={r.id} className="flex items-center gap-3 px-4 py-3 text-sm">
              <div className="min-w-0 flex-1">
                <div className="truncate font-semibold">{r.title}</div>
                <div className="truncate text-xs text-sand-500">{r.owner} · {r.kind} · {r.tokens} tokens · {duration(r.durationMs)}{r.error ? ` · ${r.error}` : ''}</div>
              </div>
              <span className="text-xs text-sand-500">{timeAgo(r.createdAt)}</span>
              <StatusBadge status={r.status} />
            </li>
          ))}
          {!data?.length && <li className="p-6 text-center text-sm text-sand-500">No runs.</li>}
        </ul>
      )}
    </Card>
  )
}

export default function Admin() {
  const [tab, setTab] = useState<'users' | 'health' | 'activity' | 'analytics'>('users')
  const analytics = useAnalytics(30, true)
  return (
    <>
      <PageHeader eyebrow="Administration" title="Admin" subtitle="Manage users, watch platform activity and the health of every service."
        actions={<Link to="/api/docs" target="_blank" className="text-xs text-ember-400 hover:underline">API docs ↗</Link>} />
      <div className="mb-5 overflow-x-auto">
        <Tabs value={tab} onChange={setTab} items={[
          { value: 'users', label: 'Users', icon: <Users className="size-4" /> },
          { value: 'health', label: 'System health', icon: <HeartPulse className="size-4" /> },
          { value: 'activity', label: 'Activity', icon: <Activity className="size-4" /> },
          { value: 'analytics', label: 'Platform analytics', icon: <BarChart3 className="size-4" /> },
        ]} />
      </div>
      {tab === 'users' && <UsersTab />}
      {tab === 'health' && <HealthTab />}
      {tab === 'activity' && <ActivityTab />}
      {tab === 'analytics' && <AnalyticsView data={analytics.data} loading={analytics.isLoading} />}
    </>
  )
}
