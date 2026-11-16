import { ArrowLeft, Pencil, Play, Square } from 'lucide-react'
import { useMemo, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { CrewNetwork } from '../../components/CrewNetwork'
import { Markdown } from '../../components/Markdown'
import { RunTimeline } from '../../components/RunTimeline'
import { Button, Card, PageHeader, Pill, Skeleton, StatusBadge, Textarea } from '../../components/ui'
import { post } from '../../lib/api'
import { duration, timeAgo } from '../../lib/format'
import { useCreateRun, useCrew, useRuns } from '../../lib/queries'
import { useLiveRun } from '../../lib/useLiveRun'

export default function CrewDetail() {
  const { id } = useParams()
  const { data: crew, isLoading } = useCrew(id)
  const [input, setInput] = useState('')
  const [runId, setRunId] = useState<string>()
  const createRun = useCreateRun()
  const { run, steps, live } = useLiveRun(runId)
  const history = useRuns({ crew: `/api/crews/${id}`, itemsPerPage: 6 })
  const running = run && (run.status === 'queued' || run.status === 'running')

  // The member currently holding the floor = target of the last hand-off.
  const activeRole = useMemo(() => {
    if (!running) return null
    const handoff = [...steps].reverse().find((s) => s.type === 'handoff')
    const to = handoff?.metadata.to as string | undefined
    return to && to !== 'FINISH' ? to : null
  }, [steps, running])

  const start = async (e: FormEvent) => {
    e.preventDefault()
    if (!crew || !input.trim()) return
    const created = await createRun.mutateAsync({ kind: 'crew', crew: crew['@id'], input })
    setRunId(created.id)
  }

  if (isLoading) return <Skeleton className="h-96" />
  if (!crew) return <p className="text-sand-400">Crew not found.</p>

  return (
    <>
      <Link to="/crews" className="mb-4 inline-flex items-center gap-1 text-sm text-sand-400 hover:text-sand-50"><ArrowLeft className="size-4" />Crews</Link>
      <PageHeader eyebrow="Agentic AI" title={crew.name} subtitle={crew.goal}
        actions={<Link to={`/crews/${crew.id}/edit`}><Button variant="secondary" icon={<Pencil className="size-4" />}>Edit</Button></Link>} />

      <div className="grid gap-6 lg:grid-cols-[360px_1fr]">
        <div className="space-y-4">
          <Card>
            <CrewNetwork members={crew.members.map((m) => ({ role: m.role, name: m.agent.name }))} active={activeRole} supervisor={crew.strategy === 'supervisor'} />
            <div className="mt-4 flex flex-wrap justify-center gap-1.5">
              <Pill className="capitalize">{crew.strategy}</Pill>
              <Pill>max {crew.maxRounds} rounds</Pill>
              <Pill>{crew.supervisorProvider} · {crew.supervisorModel}</Pill>
            </div>
          </Card>
          <Card>
            <h3 className="mb-2 font-display text-sm font-bold">Previous runs</h3>
            <ul className="space-y-1">
              {history.data?.member.map((r) => (
                <li key={r.id}>
                  <button onClick={() => setRunId(r.id)} className="flex w-full items-center gap-2 rounded-lg p-2 text-left hover:bg-white/[0.04]">
                    <span className="min-w-0 flex-1"><span className="block truncate text-sm">{r.input}</span><span className="text-[11px] text-sand-500">{timeAgo(r.createdAt)} · {duration(r.durationMs)}</span></span>
                    <StatusBadge status={r.status} />
                  </button>
                </li>
              ))}
              {!history.data?.member.length && <li className="text-xs text-sand-500">No runs yet.</li>}
            </ul>
          </Card>
        </div>

        <div className="space-y-4">
          <form onSubmit={start} className="glass p-4">
            <label className="label" htmlFor="crew-input">Request for the crew</label>
            <Textarea id="crew-input" rows={3} value={input} onChange={(e) => setInput(e.target.value)} placeholder="e.g. Prepare a brief about vector databases with a Python example." />
            <div className="mt-3 flex justify-end gap-2">
              {running && <Button type="button" variant="secondary" onClick={() => run && post(`/api/runs/${run.id}/cancel`)} icon={<Square className="size-4" />}>Stop</Button>}
              <Button type="submit" loading={createRun.isPending} disabled={!input.trim() || !!running} icon={<Play className="size-4" />}>Run crew</Button>
            </div>
          </form>

          {run && (
            <Card>
              <div className="mb-4 flex flex-wrap items-center gap-2 text-xs text-sand-400">
                <StatusBadge status={run.status} />
                <span>{steps.length} steps</span>
                {run.durationMs !== null && <span>· {duration(run.durationMs)}</span>}
                <Link to={`/runs/${run.id}`} className="ml-auto text-ember-400 hover:underline">Full run →</Link>
              </div>
              <RunTimeline steps={steps.filter((s) => s.type !== 'final')} live={live} running={!!running} />
              {run.status === 'succeeded' && run.output && (
                <div className="mt-5 rounded-2xl border border-ember-500/25 bg-ember-500/[0.04] p-4">
                  <div className="mb-2 font-display text-xs font-bold tracking-widest text-ember-300 uppercase">Team result</div>
                  <Markdown>{run.output}</Markdown>
                </div>
              )}
              {run.status === 'failed' && <p className="mt-4 rounded-xl border border-bad-500/30 bg-bad-500/10 p-3 text-sm text-red-300">{run.error}</p>}
            </Card>
          )}
        </div>
      </div>
    </>
  )
}
