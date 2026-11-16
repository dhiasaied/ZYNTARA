import clsx from 'clsx'
import { motion } from 'framer-motion'
import { useNavigate } from 'react-router'
import { LEVELS } from '../lib/format'
import type { LadderCount, Level } from '../lib/types'

const LINKS: Record<Level, string> = {
  prompt: '/playground',
  app: '/agents/new?level=app',
  tools: '/agents/new?level=tools',
  rag: '/knowledge',
  agent: '/agents/new?level=agent',
  agentic: '/crews/new',
}

/** The staircase from the end of the video: PROMPT → APP → TOOLS → RAG → AGENT → AGENTIC. */
export function Ladder({ counts, highlight }: { counts?: LadderCount[]; highlight?: Level }) {
  const navigate = useNavigate()
  const byLevel = Object.fromEntries((counts ?? []).map((c) => [c.level, c.count])) as Partial<Record<Level, number>>
  const reached = LEVELS.filter((l) => (byLevel[l.key] ?? 0) > 0).length

  return (
    <div className="relative">
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-end sm:gap-2">
        {LEVELS.map((level, i) => {
          const count = byLevel[level.key] ?? 0
          const active = count > 0 || highlight === level.key
          return (
            <motion.button
              key={level.key}
              type="button"
              onClick={() => navigate(LINKS[level.key])}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.07, type: 'spring', damping: 20 }}
              whileHover={{ y: -3 }}
              className={clsx(
                'group relative flex-1 rounded-xl border p-3 text-left transition sm:min-h-0',
                active ? 'border-ember-500/40 bg-gradient-to-b from-ember-500/15 to-ink-850' : 'border-white/[0.07] bg-ink-850/80 hover:border-white/15',
              )}
              style={{ marginTop: 0 }}
            >
              <div className="hidden sm:block" style={{ height: i * 18 }} />
              <div className="flex items-center justify-between">
                <span className={clsx('font-display text-[11px] font-extrabold tracking-[0.18em]', active ? 'text-ember-300' : 'text-sand-400')}>{level.short}</span>
                <span className={clsx('rounded-md px-1.5 text-xs font-bold tabular-nums', active ? 'bg-ember-500 text-ink-950' : 'bg-white/5 text-sand-500')}>{count}</span>
              </div>
              <p className="mt-1.5 text-xs leading-snug text-sand-400 group-hover:text-sand-200">{level.blurb}</p>
            </motion.button>
          )
        })}
      </div>
      <div className="mt-3 flex items-center gap-3 text-xs text-sand-400">
        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-white/5">
          <motion.div className="h-full rounded-full bg-gradient-to-r from-ember-600 to-ember-300" initial={{ width: 0 }} animate={{ width: `${(reached / LEVELS.length) * 100}%` }} transition={{ duration: 0.9, ease: 'easeOut' }} />
        </div>
        <span className="tabular-nums">{reached}/{LEVELS.length} rungs climbed</span>
      </div>
    </div>
  )
}
