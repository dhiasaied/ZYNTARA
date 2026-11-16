import { ArrowLeft, RotateCcw, Square } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'
import { Markdown } from '../../components/Markdown'
import { RunTimeline } from '../../components/RunTimeline'
import { Button, Card, PageHeader, Pill, Skeleton, StatusBadge } from '../../components/ui'
import { post } from '../../lib/api'
import { duration } from '../../lib/format'
import { useLiveRun } from '../../lib/useLiveRun'

export default function RunDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { run, steps, live, isLoading } = useLiveRun(id)

  if (isLoading) return <Skeleton className="h-96" />
  if (!run) return <p className="text-sand-400">Run not found.</p>
  const active = run.status === 'queued' || run.status === 'running'

  const facts: [string, string][] = [
    ['Type', run.kind],
    ['Started', run.startedAt ? new Date(run.startedAt).toLocaleString() : '—'],
    ['Duration', duration(run.durationMs)],
    ['Tokens in / out', `${run.tokensIn} / ${run.tokensOut}`],
    ['Steps', String(steps.length)],
  ]
  if (run.model) facts.splice(1, 0, ['Model', `${run.provider} · ${run.model}`])

  return (
    <>
      <Link to="/runs" className="mb-4 inline-flex items-center gap-1 text-sm text-sand-400 hover:text-sand-50"><ArrowLeft className="size-4" />Runs</Link>
      <PageHeader eyebrow={`Run · ${run.id.slice(0, 8)}`} title={run.title}
        actions={<>
          <StatusBadge status={run.status} />
          {active && <Button variant="secondary" icon={<Square className="size-4" />} onClick={async () => { await post(`/api/runs/${run.id}/cancel`); toast.success('Cancellation requested') }}>Stop</Button>}
          {!active && !run.conversation && <Button variant="secondary" icon={<RotateCcw className="size-4" />} onClick={async () => {
            const res = await post<{ id: string }>(`/api/runs/${run.id}/retry`)
            navigate(`/runs/${res.id}`)
          }}>Run again</Button>}
        </>} />

      <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
        <div className="space-y-4">
          <Card>
            <div className="label">Input</div>
            <p className="text-sm whitespace-pre-wrap text-sand-200">{run.input}</p>
          </Card>
          <Card>
            <h2 className="mb-4 font-display font-bold">Reasoning trace</h2>
            {steps.length || active ? <RunTimeline steps={steps} live={live} running={active} /> : <p className="text-sm text-sand-500">No steps were recorded.</p>}
          </Card>
          {run.status === 'succeeded' && run.output && (
            <Card glow>
              <div className="mb-2 font-display text-xs font-bold tracking-widest text-ember-300 uppercase">Output</div>
              <Markdown>{run.output}</Markdown>
            </Card>
          )}
          {run.error && <Card className="border-bad-500/30"><div className="label text-red-300">Error</div><p className="text-sm text-red-200">{run.error}</p></Card>}
        </div>

        <Card className="h-fit space-y-3 lg:sticky lg:top-8">
          {facts.map(([k, v]) => (
            <div key={k} className="flex justify-between gap-3 text-sm"><span className="text-sand-500">{k}</span><span className="text-right font-medium capitalize">{v}</span></div>
          ))}
          {run.toolsUsed.length > 0 && (
            <div><div className="mb-1.5 text-sm text-sand-500">Tools used</div><div className="flex flex-wrap gap-1.5">{run.toolsUsed.map((t) => <Pill key={t}>{t}</Pill>)}</div></div>
          )}
          {run.agent && <Link to={`/agents/${run.agent.id}`} className="block text-sm text-ember-400 hover:underline">Open agent {run.agent.name} →</Link>}
          {run.crew && <Link to={`/crews/${run.crew.id}`} className="block text-sm text-ember-400 hover:underline">Open crew {run.crew.name} →</Link>}
        </Card>
      </div>
    </>
  )
}
