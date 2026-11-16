import clsx from 'clsx'
import { motion } from 'framer-motion'
import { Bot, Crown } from 'lucide-react'

/**
 * The "agents on a dotted network" scene from the video: a supervisor in the centre and
 * members around it. The active member glows and the link to it animates.
 */
export function CrewNetwork({ members, active, supervisor = true }: { members: { role: string; name: string }[]; active?: string | null; supervisor?: boolean }) {
  const size = 320
  const c = size / 2
  const r = size / 2 - 46
  const points = members.map((m, i) => {
    const angle = (i / Math.max(members.length, 1)) * Math.PI * 2 - Math.PI / 2
    return { ...m, x: c + r * Math.cos(angle), y: c + r * Math.sin(angle) }
  })

  return (
    <div className="relative mx-auto aspect-square w-full max-w-[320px]">
      <svg viewBox={`0 0 ${size} ${size}`} className="absolute inset-0 size-full" aria-hidden>
        <circle cx={c} cy={c} r={r} fill="none" stroke="rgb(255 255 255 / 0.06)" strokeDasharray="2 6" />
        {points.map((p, i) => {
          const next = points[(i + 1) % points.length]
          return points.length > 2 ? <line key={`ring-${i}`} x1={p.x} y1={p.y} x2={next.x} y2={next.y} stroke="rgb(255 106 26 / 0.18)" strokeDasharray="3 5" /> : null
        })}
        {supervisor && points.map((p) => (
          <line key={`spoke-${p.role}`} x1={c} y1={c} x2={p.x} y2={p.y}
            stroke={active === p.role ? 'rgb(255 138 61)' : 'rgb(255 106 26 / 0.3)'} strokeWidth={active === p.role ? 2 : 1}
            strokeDasharray="4 6" className={active === p.role ? 'animate-[dash_1s_linear_infinite]' : ''} />
        ))}
      </svg>
      <style>{'@keyframes dash { to { stroke-dashoffset: -20; } }'}</style>
      {supervisor && (
        <div className="absolute -translate-x-1/2 -translate-y-1/2" style={{ left: '50%', top: '50%' }}>
          <div className={clsx('tile grid size-16 place-items-center text-ember-400', !active && 'shadow-glow')}><Crown className="size-7" /></div>
          <div className="mt-1 text-center text-[10px] font-bold tracking-widest text-sand-400 uppercase">Supervisor</div>
        </div>
      )}
      {points.map((p, i) => (
        <motion.div key={p.role} className="absolute -translate-x-1/2 -translate-y-1/2 text-center" style={{ left: `${(p.x / size) * 100}%`, top: `${(p.y / size) * 100}%` }}
          initial={{ scale: 0, opacity: 0 }} animate={{ scale: active === p.role ? 1.1 : 1, opacity: 1 }} transition={{ delay: i * 0.08, type: 'spring', damping: 14 }}>
          <div className={clsx('tile relative grid size-12 place-items-center', active === p.role ? 'text-ember-300 shadow-glow' : 'text-ember-500')}>
            <Bot className="size-6" />
            <span className="absolute -top-1.5 -right-1.5 grid size-5 place-items-center rounded-md bg-ember-500 text-[10px] font-black text-ink-950">{i + 1}</span>
          </div>
          <div className="mt-1 max-w-24 truncate text-[11px] font-semibold">{p.role}</div>
          <div className="max-w-24 truncate text-[10px] text-sand-500">{p.name}</div>
        </motion.div>
      ))}
    </div>
  )
}
