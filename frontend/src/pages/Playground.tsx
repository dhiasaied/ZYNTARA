import { motion } from 'framer-motion'
import { ArrowUp, Cpu, Square } from 'lucide-react'
import { useCallback, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Markdown } from '../components/Markdown'
import { ModelPicker } from '../components/ModelPicker'
import { Button, Card, ChipIcon, PageHeader, StatusBadge, Textarea } from '../components/ui'
import { post } from '../lib/api'
import { duration, timeAgo } from '../lib/format'
import { useCreateRun, useRuns } from '../lib/queries'
import type { Provider } from '../lib/types'
import { useLiveRun } from '../lib/useLiveRun'
import { useAuth } from '../stores/auth'

/** PROMPT rung: "you ask the LLM, it answers." */
export default function Playground() {
  const prefs = useAuth((s) => s.user?.preferences)
  const [provider, setProvider] = useState<Provider>(prefs?.defaultProvider ?? 'mock')
  const [model, setModel] = useState(prefs?.defaultModel ?? 'zyntara-mock-1')
  const [temperature, setTemperature] = useState(0.7)
  const [prompt, setPrompt] = useState('')
  const [runId, setRunId] = useState<string>()
  const createRun = useCreateRun()
  const { run, live } = useLiveRun(runId)
  const history = useRuns({ kind: 'prompt', itemsPerPage: 8 })
  const onModel = useCallback((p: Provider, m: string) => { setProvider(p); setModel(m) }, [])

  const running = run && (run.status === 'queued' || run.status === 'running')

  const submit = async (e?: FormEvent) => {
    e?.preventDefault()
    if (!prompt.trim()) return
    const created = await createRun.mutateAsync({ kind: 'prompt', input: prompt, provider, model, temperature })
    setRunId(created.id)
  }

  return (
    <>
      <PageHeader eyebrow="Rung 1 · Prompt" title="Playground" subtitle="Talk to a model directly. This is the raw LLM: no system prompt, no tools, no documents — just your prompt and its answer." />

      <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
        <div className="space-y-4">
          <Card>
            <ModelPicker provider={provider} model={model} onChange={onModel} />
            <div className="mt-4">
              <label className="label flex justify-between"><span>Temperature</span><span className="font-mono text-ember-300">{temperature.toFixed(1)}</span></label>
              <input type="range" min={0} max={2} step={0.1} value={temperature} onChange={(e) => setTemperature(Number(e.target.value))} className="w-full accent-ember-500" />
            </div>
          </Card>

          <Card className="min-h-[280px]">
            {!run ? (
              <div className="flex flex-col items-center py-10 text-center">
                <div className="flex items-center gap-3 font-display text-xs font-bold tracking-widest text-sand-400">
                  <span className="rounded-md border border-white/10 px-2 py-1">PROMPT</span>
                  <span className="dotted-line h-px w-10" />
                  <ChipIcon active><Cpu /></ChipIcon>
                  <span className="dotted-line h-px w-10" />
                  <span className="rounded-md border border-white/10 px-2 py-1">ANSWER</span>
                </div>
                <p className="mt-6 max-w-sm text-sm text-sand-400">Write a prompt below. Try the same prompt with different models and temperatures to feel the difference.</p>
              </div>
            ) : (
              <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
                <div className="mb-3 flex flex-wrap items-center gap-2 text-xs text-sand-400">
                  <StatusBadge status={run.status} />
                  <span>{run.model}</span>
                  {run.durationMs !== null && <span>· {duration(run.durationMs)}</span>}
                  {run.tokensOut > 0 && <span>· {run.tokensIn + run.tokensOut} tokens</span>}
                  <Link to={`/runs/${run.id}`} className="ml-auto text-ember-400 hover:underline">Open run</Link>
                </div>
                <div className="mb-4 rounded-xl bg-white/[0.03] p-3 text-sm text-sand-200">{run.input}</div>
                {run.status === 'succeeded' && run.output && <Markdown>{run.output}</Markdown>}
                {running && (live?.text ? <Markdown>{live.text + ' ▍'}</Markdown> : <p className="animate-pulse text-sm text-sand-400">Thinking…</p>)}
                {run.status === 'failed' && <p className="rounded-xl border border-bad-500/30 bg-bad-500/10 p-3 text-sm text-red-300">{run.error}</p>}
                {run.status === 'cancelled' && <p className="text-sm text-sand-400">Cancelled.</p>}
              </motion.div>
            )}
          </Card>

          <form onSubmit={submit} className="glass flex items-end gap-2 p-2">
            <Textarea value={prompt} onChange={(e) => setPrompt(e.target.value)} placeholder="Ask anything…" rows={2}
              className="min-h-12 border-0 bg-transparent focus:ring-0"
              onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void submit() } }} />
            {running ? (
              <Button type="button" variant="secondary" aria-label="Stop" onClick={() => run && post(`/api/runs/${run.id}/cancel`)} icon={<Square className="size-4" />} />
            ) : (
              <Button type="submit" aria-label="Send" loading={createRun.isPending} disabled={!prompt.trim()} icon={<ArrowUp className="size-4" />} />
            )}
          </form>
        </div>

        <Card className="h-fit">
          <h3 className="mb-3 font-display text-sm font-bold">Recent prompts</h3>
          <ul className="space-y-1">
            {history.data?.member.map((r) => (
              <li key={r.id}>
                <button onClick={() => { setRunId(r.id); setPrompt(r.input) }} className="w-full rounded-lg p-2 text-left transition hover:bg-white/[0.04]">
                  <div className="truncate text-sm">{r.input}</div>
                  <div className="text-[11px] text-sand-500">{r.model} · {timeAgo(r.createdAt)}</div>
                </button>
              </li>
            ))}
            {!history.data?.member.length && <li className="text-xs text-sand-500">Nothing yet.</li>}
          </ul>
        </Card>
      </div>
    </>
  )
}
