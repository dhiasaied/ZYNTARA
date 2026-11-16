import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { Ladder } from '../components/Ladder'
import { Card, Skeleton } from '../components/ui'
import { compact, duration } from '../lib/format'
import type { Analytics } from '../lib/types'

const tooltip = { contentStyle: { background: '#171411', border: '1px solid rgba(255,255,255,.08)', borderRadius: 12 }, labelStyle: { color: '#a39a90' } }

/** Shared by the user analytics page and the admin (platform-wide) view. */
export function AnalyticsView({ data, loading }: { data?: Analytics; loading: boolean }) {
  if (loading || !data) return <Skeleton className="h-[60vh]" />
  const kpis = [
    ['Runs', compact(data.totals.runs)],
    ['Success rate', data.totals.successRate === null ? '—' : `${data.totals.successRate}%`],
    ['Tokens', compact(data.totals.tokensIn + data.totals.tokensOut)],
    ['Avg duration', duration(data.totals.avgDurationMs)],
    ['p95 duration', duration(data.totals.p95DurationMs)],
    ...(data.users !== undefined ? [['Users', String(data.users)]] : []),
  ]

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        {kpis.map(([label, value]) => (
          <Card key={label} className="p-4"><div className="font-display text-2xl font-extrabold tabular-nums">{value}</div><div className="text-xs text-sand-400">{label}</div></Card>
        ))}
      </div>

      <Card>
        <h2 className="mb-4 font-display font-bold">Runs over time</h2>
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={data.series}>
              <defs>
                <linearGradient id="okFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#FF6A1A" stopOpacity={0.4} /><stop offset="100%" stopColor="#FF6A1A" stopOpacity={0} /></linearGradient>
              </defs>
              <CartesianGrid stroke="rgba(255,255,255,.05)" vertical={false} />
              <XAxis dataKey="date" tickFormatter={(d: string) => new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })} stroke="#7d756c" fontSize={11} tickLine={false} axisLine={false} minTickGap={24} />
              <YAxis stroke="#7d756c" fontSize={11} tickLine={false} axisLine={false} allowDecimals={false} width={30} />
              <Tooltip {...tooltip} />
              <Area type="monotone" dataKey="succeeded" stackId="1" stroke="#FF8A3D" fill="url(#okFill)" strokeWidth={2} />
              <Area type="monotone" dataKey="failed" stackId="1" stroke="#ef4444" fill="rgba(239,68,68,.15)" strokeWidth={1.5} />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </Card>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="mb-4 font-display font-bold">Tool usage</h2>
          {data.tools.length ? (
            <div className="h-56">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data.tools} layout="vertical" margin={{ left: 20 }}>
                  <XAxis type="number" hide />
                  <YAxis type="category" dataKey="tool" stroke="#a39a90" fontSize={12} tickLine={false} axisLine={false} width={120} />
                  <Tooltip {...tooltip} cursor={{ fill: 'rgba(255,255,255,.03)' }} />
                  <Bar dataKey="count" fill="#FF6A1A" radius={[0, 6, 6, 0]} barSize={16} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          ) : <p className="text-sm text-sand-500">No tool calls in this period.</p>}
        </Card>
        <Card>
          <h2 className="mb-4 font-display font-bold">Most used agents</h2>
          <ul className="space-y-2">
            {data.topAgents.map((a) => {
              const max = data.topAgents[0]?.runs || 1
              return (
                <li key={a.id}>
                  <div className="mb-1 flex justify-between text-sm"><span className="truncate">{a.name}</span><span className="tabular-nums text-sand-400">{a.runs}</span></div>
                  <div className="h-1.5 rounded-full bg-white/5"><div className="h-full rounded-full bg-gradient-to-r from-ember-600 to-ember-400" style={{ width: `${(a.runs / max) * 100}%` }} /></div>
                </li>
              )
            })}
            {!data.topAgents.length && <li className="text-sm text-sand-500">No agent runs in this period.</li>}
          </ul>
          <div className="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
            {Object.entries(data.byKind).map(([k, v]) => <div key={k} className="rounded-lg bg-white/[0.03] py-2"><div className="font-display text-base font-bold">{v}</div><div className="text-sand-500 capitalize">{k}</div></div>)}
          </div>
        </Card>
      </div>

      <Card>
        <h2 className="mb-4 font-display font-bold">Ladder distribution</h2>
        <Ladder counts={data.ladder} />
      </Card>
    </div>
  )
}
