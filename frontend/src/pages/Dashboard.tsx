import { motion } from 'framer-motion'
import { Activity, ArrowRight, Bot, BookOpenText, Network, Plus, Sparkles } from 'lucide-react'
import { Link } from 'react-router'
import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis } from 'recharts'
import { Ladder } from '../components/Ladder'
import { Button, Card, ChipIcon, PageHeader, Skeleton, StatusBadge } from '../components/ui'
import { duration, timeAgo } from '../lib/format'
import { useDashboard } from '../lib/queries'
import { useAuth } from '../stores/auth'

export default function Dashboard() {
  const { data, isLoading } = useDashboard()
  const user = useAuth((s) => s.user)

  const stats = [
    { label: 'Agents', value: data?.counts.agents, icon: <Bot />, to: '/agents' },
    { label: 'Crews', value: data?.counts.crews, icon: <Network />, to: '/crews' },
    { label: 'Documents', value: data?.counts.documents, icon: <BookOpenText />, to: '/knowledge' },
    { label: 'Runs', value: data?.counts.runs, icon: <Activity />, to: '/runs', extra: data?.counts.activeRuns ? `${data.counts.activeRuns} active` : undefined },
  ]

  return (
    <>
      <PageHeader
        eyebrow="Dashboard"
        title={<>Hi {user?.fullName.split(' ')[0]}, <span className="text-gradient">keep climbing</span></>}
        subtitle="Every AI product sits on a rung of the ladder. Start with a prompt, wrap it in an app, give it tools and documents, make it autonomous, then let agents work as a team."
        actions={<>
          <Link to="/playground"><Button variant="secondary" icon={<Sparkles className="size-4" />}>Playground</Button></Link>
          <Link to="/agents/new"><Button icon={<Plus className="size-4" />}>New agent</Button></Link>
        </>}
      />

      <Card className="mb-6">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-display font-bold">Your ladder</h2>
          <span className="text-xs text-sand-500">Click a rung to build on it</span>
        </div>
        {isLoading ? <Skeleton className="h-32" /> : <Ladder counts={data?.ladder} />}
      </Card>

      <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        {stats.map((s, i) => (
          <motion.div key={s.label} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}>
            <Link to={s.to} className="glass group flex items-center gap-4 p-4 transition hover:border-ember-500/30">
              <ChipIcon size="sm">{s.icon}</ChipIcon>
              <div>
                <div className="font-display text-2xl font-extrabold tabular-nums">{isLoading ? '–' : s.value}</div>
                <div className="text-xs text-sand-400">{s.label}{s.extra && <span className="ml-1.5 text-ember-400">· {s.extra}</span>}</div>
              </div>
            </Link>
          </motion.div>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="font-display font-bold">Recent runs</h2>
            <Link to="/runs" className="flex items-center gap-1 text-xs text-ember-400 hover:underline">All runs <ArrowRight className="size-3" /></Link>
          </div>
          {isLoading ? <Skeleton className="h-48" /> : data?.recentRuns.length ? (
            <ul className="divide-y divide-white/[0.05]">
              {data.recentRuns.map((r) => (
                <li key={r.id}>
                  <Link to={`/runs/${r.id}`} className="flex items-center gap-3 py-3 transition hover:bg-white/[0.02]">
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-sm font-semibold">{r.title}</div>
                      <div className="truncate text-xs text-sand-500">{r.input}</div>
                    </div>
                    <div className="hidden text-right text-xs text-sand-500 sm:block">{duration(r.durationMs)}<br />{timeAgo(r.createdAt)}</div>
                    <StatusBadge status={r.status} />
                  </Link>
                </li>
              ))}
            </ul>
          ) : (
            <div className="py-10 text-center text-sm text-sand-400">No runs yet. <Link to="/playground" className="text-ember-400 hover:underline">Send your first prompt →</Link></div>
          )}
        </Card>

        <Card className="lg:col-span-2">
          <h2 className="mb-1 font-display font-bold">This week</h2>
          <p className="mb-4 text-xs text-sand-500">Runs per day</p>
          <div className="h-48">
            {data && (
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data.week} margin={{ left: 0, right: 0, top: 4, bottom: 0 }}>
                  <defs>
                    <linearGradient id="dashFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#FF6A1A" stopOpacity={0.45} />
                      <stop offset="100%" stopColor="#FF6A1A" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <XAxis dataKey="date" tickFormatter={(d: string) => new Date(d).toLocaleDateString(undefined, { weekday: 'short' })} stroke="#7d756c" fontSize={11} tickLine={false} axisLine={false} />
                  <Tooltip contentStyle={{ background: '#171411', border: '1px solid rgba(255,255,255,.08)', borderRadius: 12 }} labelStyle={{ color: '#a39a90' }} />
                  <Area type="monotone" dataKey="runs" stroke="#FF8A3D" strokeWidth={2} fill="url(#dashFill)" />
                </AreaChart>
              </ResponsiveContainer>
            )}
          </div>
        </Card>
      </div>
    </>
  )
}
