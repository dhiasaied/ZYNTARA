import clsx from 'clsx'
import { AnimatePresence, motion } from 'framer-motion'
import { Loader2, X } from 'lucide-react'
import {
  forwardRef, useEffect, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
} from 'react'
import type { Level, RunStatus } from '../lib/types'

// ---------------------------------------------------------------- Button
type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  icon?: ReactNode
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', loading, icon, className, children, disabled, ...rest },
  ref,
) {
  return (
    <button
      ref={ref}
      disabled={disabled || loading}
      className={clsx(
        'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all duration-150 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50',
        size === 'sm' && 'h-8 px-3 text-xs',
        size === 'md' && 'h-10 px-4 text-sm',
        size === 'lg' && 'h-12 px-6 text-base',
        variant === 'primary' && 'bg-gradient-to-b from-ember-400 to-ember-600 text-ink-950 shadow-[0_8px_30px_-8px_rgb(255_106_26/0.6)] hover:brightness-110',
        variant === 'secondary' && 'border border-white/10 bg-white/[0.04] text-sand-50 hover:border-ember-500/40 hover:bg-white/[0.07]',
        variant === 'ghost' && 'text-sand-200 hover:bg-white/[0.06] hover:text-sand-50',
        variant === 'danger' && 'border border-bad-500/30 bg-bad-500/10 text-red-300 hover:bg-bad-500/20',
        className,
      )}
      {...rest}
    >
      {loading ? <Loader2 className="size-4 animate-spin" /> : icon}
      {children}
    </button>
  )
})

// ---------------------------------------------------------------- Inputs
export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...rest }, ref) {
  return <input ref={ref} className={clsx('field', className)} {...rest} />
})

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea({ className, ...rest }, ref) {
  return <textarea ref={ref} className={clsx('field min-h-24 resize-y leading-relaxed', className)} {...rest} />
})

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function Select({ className, children, ...rest }, ref) {
  return (
    <select ref={ref} className={clsx('field appearance-none bg-[length:1rem] bg-[right_0.75rem_center] bg-no-repeat pr-9', className)}
      style={{ backgroundImage: "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a39a90' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E\")" }}
      {...rest}>
      {children}
    </select>
  )
})

export function Field({ label, error, hint, children, className }: { label: string; error?: string; hint?: string; children: ReactNode; className?: string }) {
  return (
    <label className={clsx('block', className)}>
      <span className="label">{label}</span>
      {children}
      {error ? <span className="mt-1 block text-xs text-red-400">{error}</span> : hint ? <span className="mt-1 block text-xs text-sand-500">{hint}</span> : null}
    </label>
  )
}

export function Toggle({ checked, onChange, label, description, disabled }: { checked: boolean; onChange: (v: boolean) => void; label: string; description?: string; disabled?: boolean }) {
  return (
    <button type="button" role="switch" aria-checked={checked} disabled={disabled} onClick={() => onChange(!checked)}
      className="flex w-full items-start gap-3 rounded-xl border border-white/[0.07] bg-white/[0.02] p-3 text-left transition hover:border-ember-500/30 disabled:opacity-50">
      <span className={clsx('relative mt-0.5 h-5 w-9 shrink-0 rounded-full transition', checked ? 'bg-ember-500' : 'bg-ink-600')}>
        <motion.span layout className={clsx('absolute top-0.5 size-4 rounded-full bg-white shadow', checked ? 'right-0.5' : 'left-0.5')} />
      </span>
      <span>
        <span className="block text-sm font-semibold">{label}</span>
        {description && <span className="block text-xs text-sand-400">{description}</span>}
      </span>
    </button>
  )
}

// ---------------------------------------------------------------- Surfaces
export function Card({ children, className, glow }: { children: ReactNode; className?: string; glow?: boolean }) {
  return <div className={clsx('glass p-5', glow && 'shadow-glow', className)}>{children}</div>
}

export function PageHeader({ title, subtitle, actions, eyebrow }: { title: ReactNode; subtitle?: ReactNode; actions?: ReactNode; eyebrow?: string }) {
  return (
    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        {eyebrow && <div className="mb-1 text-xs font-bold tracking-[0.2em] text-ember-400 uppercase">{eyebrow}</div>}
        <h1 className="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{title}</h1>
        {subtitle && <p className="mt-1 max-w-2xl text-sm text-sand-400">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

export function EmptyState({ icon, title, text, action }: { icon: ReactNode; title: string; text: string; action?: ReactNode }) {
  return (
    <div className="glass flex flex-col items-center px-6 py-14 text-center">
      <ChipIcon size="md">{icon}</ChipIcon>
      <h3 className="mt-5 font-display text-lg font-bold">{title}</h3>
      <p className="mt-1 max-w-sm text-sm text-sand-400">{text}</p>
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={clsx('animate-pulse rounded-xl bg-white/[0.05]', className)} />
}

export function Spinner({ className }: { className?: string }) {
  return <Loader2 className={clsx('size-5 animate-spin text-ember-400', className)} />
}

/** The glowing chip tile that represents "the AI" throughout the video. */
export function ChipIcon({ children, size = 'md', active, className }: { children: ReactNode; size?: 'sm' | 'md' | 'lg'; active?: boolean; className?: string }) {
  return (
    <div className={clsx(
      'tile relative grid place-items-center text-ember-400',
      size === 'sm' && 'size-9 rounded-xl [&_svg]:size-4',
      size === 'md' && 'size-14 [&_svg]:size-6',
      size === 'lg' && 'size-24 rounded-3xl [&_svg]:size-10',
      active && 'shadow-glow',
      className,
    )}>
      {active && <span className="absolute inset-0 rounded-[inherit] bg-ember-500/10 blur-md" />}
      <span className="relative">{children}</span>
    </div>
  )
}

// ---------------------------------------------------------------- Badges
const STATUS_STYLE: Record<RunStatus, string> = {
  queued: 'bg-sand-400/10 text-sand-200 border-sand-400/20',
  running: 'bg-ember-500/15 text-ember-300 border-ember-500/30',
  succeeded: 'bg-ok-500/10 text-green-300 border-ok-500/25',
  failed: 'bg-bad-500/10 text-red-300 border-bad-500/25',
  cancelled: 'bg-white/5 text-sand-400 border-white/10',
}

export function StatusBadge({ status }: { status: RunStatus }) {
  return (
    <span className={clsx('inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize', STATUS_STYLE[status])}>
      {(status === 'running' || status === 'queued') && <span className="size-1.5 animate-pulse rounded-full bg-current" />}
      {status}
    </span>
  )
}

const LEVEL_LABEL: Record<Level, string> = { prompt: 'Prompt', app: 'AI App', tools: 'Tools', rag: 'RAG', agent: 'AI Agent', agentic: 'Agentic' }
const LEVEL_RANK: Record<Level, number> = { prompt: 1, app: 2, tools: 3, rag: 4, agent: 5, agentic: 6 }

export function LevelBadge({ level }: { level: Level }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-md border border-ember-500/25 bg-ember-500/10 px-1.5 py-0.5 font-display text-[10px] font-bold tracking-widest text-ember-300 uppercase">
      <span className="flex items-end gap-px" aria-hidden>
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <span key={i} className={clsx('w-0.5 rounded-sm', i <= LEVEL_RANK[level] ? 'bg-ember-400' : 'bg-white/15')} style={{ height: 2 + i }} />
        ))}
      </span>
      {LEVEL_LABEL[level]}
    </span>
  )
}

export function Pill({ children, className }: { children: ReactNode; className?: string }) {
  return <span className={clsx('inline-flex items-center gap-1 rounded-md border border-white/10 bg-white/[0.04] px-1.5 py-0.5 text-[11px] text-sand-200', className)}>{children}</span>
}

// ---------------------------------------------------------------- Modal
export function Modal({ open, onClose, title, children, footer, wide }: { open: boolean; onClose: () => void; title: string; children: ReactNode; footer?: ReactNode; wide?: boolean }) {
  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open, onClose])

  return (
    <AnimatePresence>
      {open && (
        <motion.div className="fixed inset-0 z-50 grid place-items-center p-4" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
          <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={onClose} />
          <motion.div role="dialog" aria-modal="true" aria-label={title}
            className={clsx('glass relative w-full p-6', wide ? 'max-w-2xl' : 'max-w-md')}
            initial={{ y: 24, scale: 0.97 }} animate={{ y: 0, scale: 1 }} exit={{ y: 12, scale: 0.98 }} transition={{ type: 'spring', damping: 26, stiffness: 320 }}>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-display text-lg font-bold">{title}</h2>
              <button onClick={onClose} className="rounded-lg p-1 text-sand-400 hover:bg-white/5 hover:text-sand-50" aria-label="Close"><X className="size-5" /></button>
            </div>
            {children}
            {footer && <div className="mt-6 flex justify-end gap-2">{footer}</div>}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  )
}

export function ConfirmDialog({ open, onClose, onConfirm, title, text, confirmLabel = 'Delete', loading }: {
  open: boolean; onClose: () => void; onConfirm: () => void; title: string; text: string; confirmLabel?: string; loading?: boolean
}) {
  return (
    <Modal open={open} onClose={onClose} title={title}
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button variant="danger" loading={loading} onClick={onConfirm}>{confirmLabel}</Button></>}>
      <p className="text-sm text-sand-400">{text}</p>
    </Modal>
  )
}

export function Tabs<T extends string>({ value, onChange, items }: { value: T; onChange: (v: T) => void; items: { value: T; label: string; icon?: ReactNode }[] }) {
  return (
    <div className="inline-flex gap-1 rounded-xl border border-white/[0.07] bg-ink-900/60 p-1" role="tablist">
      {items.map((item) => (
        <button key={item.value} role="tab" aria-selected={value === item.value} onClick={() => onChange(item.value)}
          className={clsx('relative flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition', value === item.value ? 'text-ink-950' : 'text-sand-400 hover:text-sand-50')}>
          {value === item.value && <motion.span layoutId={`tab-${items.map((i) => i.value).join('')}`} className="absolute inset-0 rounded-lg bg-ember-500" transition={{ type: 'spring', damping: 30, stiffness: 400 }} />}
          <span className="relative flex items-center gap-1.5">{item.icon}{item.label}</span>
        </button>
      ))}
    </div>
  )
}
