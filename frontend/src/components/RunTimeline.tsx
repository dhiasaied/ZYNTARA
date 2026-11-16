import clsx from 'clsx'
import { AnimatePresence, motion } from 'framer-motion'
import {
  AlertTriangle, ArrowRightLeft, BookOpenText, Brain, CheckCircle2, ListChecks, MessageSquareText, ScanSearch, Sparkles, Wrench,
} from 'lucide-react'
import { useState, type ReactNode } from 'react'
import type { RunStep, StepType } from '../lib/types'
import { Markdown } from './Markdown'

const META: Record<StepType, { icon: ReactNode; label: string; tone: string }> = {
  plan: { icon: <ListChecks />, label: 'Plan', tone: 'text-ember-300' },
  thought: { icon: <Brain />, label: 'Thinking', tone: 'text-sand-200' },
  tool_call: { icon: <Wrench />, label: 'Tool call', tone: 'text-ember-400' },
  tool_result: { icon: <CheckCircle2 />, label: 'Tool result', tone: 'text-green-300' },
  retrieval: { icon: <BookOpenText />, label: 'Retrieval', tone: 'text-sky-300' },
  handoff: { icon: <ArrowRightLeft />, label: 'Hand-off', tone: 'text-violet-300' },
  reflection: { icon: <ScanSearch />, label: 'Self-review', tone: 'text-amber-300' },
  token: { icon: <MessageSquareText />, label: 'Streaming', tone: 'text-sand-200' },
  final: { icon: <Sparkles />, label: 'Final answer', tone: 'text-ember-300' },
  error: { icon: <AlertTriangle />, label: 'Error', tone: 'text-red-300' },
}

function StepBody({ step }: { step: RunStep }) {
  const [open, setOpen] = useState(step.type !== 'tool_result')
  if (step.type === 'retrieval') {
    const sources = (step.metadata.sources as { filename: string; chunk: number; score: number; preview: string }[] | undefined) ?? []
    return (
      <div className="space-y-2">
        <p className="text-sm text-sand-200">{step.content}</p>
        {sources.map((s, i) => (
          <div key={i} className="rounded-lg border border-white/[0.06] bg-ink-950/60 p-2.5 text-xs">
            <div className="mb-1 flex justify-between font-mono text-sky-300"><span>{s.filename} #{s.chunk}</span><span>score {s.score}</span></div>
            <p className="line-clamp-3 text-sand-400">{s.preview}</p>
          </div>
        ))}
      </div>
    )
  }
  if (step.type === 'tool_call') {
    return <code className="block rounded-lg bg-ink-950/70 p-2.5 font-mono text-xs break-all text-ember-300">{step.content}</code>
  }
  if (step.type === 'tool_result') {
    return (
      <div>
        <button onClick={() => setOpen(!open)} className="text-xs text-sand-400 underline-offset-2 hover:underline">{open ? 'Hide output' : 'Show output'}</button>
        {open && <pre className="mt-2 max-h-64 overflow-auto rounded-lg bg-ink-950/70 p-2.5 font-mono text-xs whitespace-pre-wrap text-sand-200">{step.content}</pre>}
      </div>
    )
  }
  if (step.type === 'final' || step.type === 'thought' || step.type === 'plan') {
    return <Markdown>{step.content}</Markdown>
  }
  return <p className="text-sm whitespace-pre-wrap text-sand-200">{step.content}</p>
}

export function RunTimeline({ steps, live, running }: { steps: RunStep[]; live?: { actor: string; text: string } | null; running?: boolean }) {
  return (
    <ol className="relative space-y-3">
      <span className="absolute top-2 bottom-2 left-[17px] w-px bg-gradient-to-b from-ember-500/50 via-white/10 to-transparent" aria-hidden />
      <AnimatePresence initial={false}>
        {steps.map((step) => {
          const meta = META[step.type] ?? META.thought
          return (
            <motion.li key={step.id} layout initial={{ opacity: 0, x: -12 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', damping: 24 }} className="relative flex gap-3">
              <span className={clsx('tile z-10 grid size-9 shrink-0 place-items-center rounded-xl [&_svg]:size-4', meta.tone, step.type === 'final' && 'shadow-glow')}>{meta.icon}</span>
              <div className="min-w-0 flex-1 rounded-xl border border-white/[0.06] bg-white/[0.02] p-3">
                <div className="mb-1.5 flex flex-wrap items-center gap-2 text-xs">
                  <span className={clsx('font-semibold', meta.tone)}>{meta.label}</span>
                  <span className="text-sand-500">·</span>
                  <span className="font-medium text-sand-200">{step.actor}</span>
                  <span className="ml-auto font-mono text-[10px] text-sand-500">#{step.sequence}</span>
                </div>
                <StepBody step={step} />
              </div>
            </motion.li>
          )
        })}
        {live && live.text && (
          <motion.li key="live" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="relative flex gap-3">
            <span className="tile z-10 grid size-9 shrink-0 place-items-center rounded-xl text-ember-400 [&_svg]:size-4"><MessageSquareText className="animate-pulse" /></span>
            <div className="min-w-0 flex-1 rounded-xl border border-ember-500/20 bg-ember-500/[0.04] p-3">
              <div className="mb-1.5 text-xs font-semibold text-ember-300">{live.actor} is writing…</div>
              <Markdown>{live.text + ' ▍'}</Markdown>
            </div>
          </motion.li>
        )}
      </AnimatePresence>
      {running && !live?.text && (
        <li className="relative flex items-center gap-3 pl-1 text-xs text-sand-400">
          <span className="z-10 grid size-7 place-items-center rounded-full bg-ink-900"><span className="size-2 animate-ping rounded-full bg-ember-500" /></span>
          Working…
        </li>
      )}
    </ol>
  )
}
