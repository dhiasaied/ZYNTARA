import { motion } from 'framer-motion'
import { Cpu, Eye, MessageSquare, PenLine } from 'lucide-react'
import type { ReactNode } from 'react'
import { LEVELS } from '../../lib/format'

/** Opening scene of the video: the glowing chip in its orbit, then the ladder of concepts. */
function Orbit() {
  const satellites = [
    { icon: <Eye />, angle: -150 },
    { icon: <PenLine />, angle: -30 },
    { icon: <MessageSquare />, angle: 90 },
  ]
  return (
    <div className="relative mx-auto size-72">
      <motion.div className="absolute inset-0 rounded-full border border-dashed border-white/10" animate={{ rotate: 360 }} transition={{ duration: 60, repeat: Infinity, ease: 'linear' }} />
      <svg className="absolute inset-0 size-full -rotate-90" viewBox="0 0 100 100" aria-hidden>
        <motion.circle cx="50" cy="50" r="38" fill="none" stroke="url(#ember)" strokeWidth="1.2" strokeLinecap="round"
          initial={{ pathLength: 0 }} animate={{ pathLength: 0.72 }} transition={{ duration: 2.2, ease: 'easeInOut' }} />
        <defs><linearGradient id="ember"><stop offset="0" stopColor="#E5520A" /><stop offset="1" stopColor="#FFB27A" /></linearGradient></defs>
      </svg>
      <div className="absolute inset-0 grid place-items-center">
        <motion.div className="tile grid size-24 place-items-center rounded-3xl text-ember-400 shadow-glow" initial={{ scale: 0.6, opacity: 0 }} animate={{ scale: 1, opacity: 1 }} transition={{ type: 'spring', damping: 12, delay: 0.2 }}>
          <Cpu className="size-11" />
        </motion.div>
      </div>
      {satellites.map((s, i) => {
        const rad = (s.angle * Math.PI) / 180
        return (
          <motion.div key={i} className="tile absolute grid size-11 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-xl text-ember-500 [&_svg]:size-5"
            style={{ left: `${50 + 47 * Math.cos(rad)}%`, top: `${50 + 47 * Math.sin(rad)}%` }}
            initial={{ opacity: 0, scale: 0 }} animate={{ opacity: 1, scale: 1 }} transition={{ delay: 0.8 + i * 0.2, type: 'spring' }}>
            {s.icon}
          </motion.div>
        )
      })}
    </div>
  )
}

export function AuthLayout({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <section className="relative hidden flex-col justify-between overflow-hidden border-r border-white/[0.06] p-12 lg:flex">
        <div className="flex items-center gap-2.5">
          <img src="/zyntara.svg" alt="" className="size-9" />
          <span className="font-display text-xl font-extrabold">ZYNTARA</span>
        </div>
        <div>
          <Orbit />
          <h2 className="mt-10 font-display text-4xl leading-tight font-black">
            From <span className="text-gradient">LLM</span> to <span className="text-gradient">Agentic AI</span>,<br />one rung at a time.
          </h2>
          <div className="mt-6 flex flex-wrap gap-2">
            {LEVELS.map((l, i) => (
              <motion.span key={l.key} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 1.2 + i * 0.1 }}
                className="rounded-lg border border-white/10 bg-ink-850 px-2.5 py-1 font-display text-[11px] font-bold tracking-widest text-sand-200">
                {l.short}
              </motion.span>
            ))}
          </div>
        </div>
        <p className="text-sm text-sand-500">AI in a minute: from LLM to Agentic AI</p>
      </section>
      <section className="flex items-center justify-center p-6">
        <motion.div className="w-full max-w-sm" initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}>
          <div className="mb-8 lg:hidden"><img src="/zyntara.svg" alt="" className="size-10" /></div>
          <h1 className="font-display text-3xl font-extrabold">{title}</h1>
          <p className="mt-1 mb-8 text-sm text-sand-400">{subtitle}</p>
          {children}
        </motion.div>
      </section>
    </div>
  )
}
