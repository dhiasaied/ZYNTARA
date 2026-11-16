import { useQueryClient } from '@tanstack/react-query'
import clsx from 'clsx'
import { AnimatePresence, motion } from 'framer-motion'
import {
  Activity, BarChart3, Bell, Bot, BookOpenText, LayoutDashboard, LogOut, Menu, Network, Settings, Shield, Sparkles, X,
} from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { useUnreadCount } from '../lib/queries'
import { connectRealtime, useRealtimeEvent, useRealtimeStatus } from '../lib/realtime'
import { useAuth } from '../stores/auth'

const NAV: { to: string; label: string; icon: ReactNode; rung?: string }[] = [
  { to: '/', label: 'Dashboard', icon: <LayoutDashboard /> },
  { to: '/playground', label: 'Playground', icon: <Sparkles />, rung: 'Prompt' },
  { to: '/agents', label: 'Agents', icon: <Bot />, rung: 'App · Tools · Agent' },
  { to: '/knowledge', label: 'Knowledge', icon: <BookOpenText />, rung: 'RAG' },
  { to: '/crews', label: 'Crews', icon: <Network />, rung: 'Agentic' },
  { to: '/runs', label: 'Runs', icon: <Activity /> },
  { to: '/analytics', label: 'Analytics', icon: <BarChart3 /> },
]

function Logo() {
  return (
    <div className="flex items-center gap-2.5">
      <img src="/zyntara.svg" alt="" className="size-8" />
      <div>
        <div className="font-display text-lg leading-none font-extrabold tracking-tight">ZYNTARA</div>
        <div className="text-[10px] leading-tight text-ember-400">agentic AI studio</div>
      </div>
    </div>
  )
}

function NavItem({ to, label, icon, rung, badge, onClick }: { to: string; label: string; icon: ReactNode; rung?: string; badge?: number; onClick?: () => void }) {
  return (
    <NavLink to={to} end={to === '/'} onClick={onClick}
      className={({ isActive }) => clsx(
        'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition [&_svg]:size-[18px]',
        isActive ? 'bg-ember-500/12 text-sand-50' : 'text-sand-400 hover:bg-white/[0.04] hover:text-sand-50',
      )}>
      {({ isActive }) => (
        <>
          {isActive && <motion.span layoutId="nav-active" className="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-ember-500" />}
          <span className={isActive ? 'text-ember-400' : ''}>{icon}</span>
          <span className="flex-1">
            {label}
            {rung && <span className="block text-[10px] font-normal text-sand-500">{rung}</span>}
          </span>
          {!!badge && <span className="rounded-full bg-ember-500 px-1.5 text-[10px] font-bold text-ink-950">{badge > 99 ? '99+' : badge}</span>}
        </>
      )}
    </NavLink>
  )
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const { data: unread } = useUnreadCount()
  const realtime = useRealtimeStatus((s) => s.status)

  return (
    <div className="flex h-full flex-col gap-6 p-4">
      <div className="px-2 pt-1"><Logo /></div>
      <nav className="flex flex-col gap-0.5">
        {NAV.map((item) => <NavItem key={item.to} {...item} onClick={onNavigate} />)}
      </nav>
      <div className="mt-auto flex flex-col gap-0.5">
        <NavItem to="/notifications" label="Notifications" icon={<Bell />} badge={unread?.count} onClick={onNavigate} />
        <NavItem to="/settings" label="Settings" icon={<Settings />} onClick={onNavigate} />
        {user?.isAdmin && <NavItem to="/admin" label="Admin" icon={<Shield />} onClick={onNavigate} />}
        <div className="mt-3 flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.02] p-2.5">
          <div className="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-ember-400 to-ember-700 font-display font-bold text-ink-950">
            {user?.fullName?.[0]?.toUpperCase() ?? '?'}
          </div>
          <div className="min-w-0 flex-1">
            <div className="truncate text-sm font-semibold">{user?.fullName}</div>
            <div className="flex items-center gap-1.5 text-[10px] text-sand-500">
              <span className={clsx('size-1.5 rounded-full', realtime === 'live' ? 'bg-ok-500' : realtime === 'connecting' ? 'bg-warn-500' : 'bg-sand-500')} />
              {realtime === 'live' ? 'Live updates' : realtime === 'connecting' ? 'Connecting…' : 'Polling'}
            </div>
          </div>
          <button aria-label="Sign out" title="Sign out" onClick={() => { logout(); navigate('/login') }} className="rounded-lg p-1.5 text-sand-400 hover:bg-white/5 hover:text-sand-50">
            <LogOut className="size-4" />
          </button>
        </div>
      </div>
    </div>
  )
}

/** Global realtime side effects: toasts and cache invalidation. */
function RealtimeBridge() {
  const qc = useQueryClient()
  const navigate = useNavigate()
  useEffect(() => connectRealtime(), [])

  useRealtimeEvent('notification', (n) => {
    qc.invalidateQueries({ queryKey: ['notifications'] })
    if (useAuth.getState().user?.preferences.toastNotifications === false) return
    const type = String(n.type)
    const show = type.endsWith('failed') ? toast.error : toast.success
    show(String(n.title), {
      description: n.body ? String(n.body) : undefined,
      action: n.link ? { label: 'Open', onClick: () => navigate(String(n.link)) } : undefined,
    })
  })
  useRealtimeEvent('run.status', (r) => {
    qc.invalidateQueries({ queryKey: ['runs'] })
    qc.invalidateQueries({ queryKey: ['dashboard'] })
    if (r.conversationId && ['succeeded', 'failed'].includes(String(r.status))) {
      qc.invalidateQueries({ queryKey: ['conversation', String(r.conversationId)] })
    }
  })
  useRealtimeEvent('document.status', (d) => {
    qc.invalidateQueries({ queryKey: ['documents', String(d.knowledgeBaseId)] })
    qc.invalidateQueries({ queryKey: ['kb', String(d.knowledgeBaseId)] })
    qc.invalidateQueries({ queryKey: ['kbs'] })
  })
  return null
}

export function AppShell() {
  const [open, setOpen] = useState(false)
  const location = useLocation()
  useEffect(() => setOpen(false), [location.pathname])

  return (
    <div className="min-h-screen lg:pl-64">
      <RealtimeBridge />
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-white/[0.06] bg-ink-950/80 backdrop-blur-xl lg:block">
        <Sidebar />
      </aside>

      <header className="sticky top-0 z-20 flex items-center justify-between border-b border-white/[0.06] bg-ink-950/80 px-4 py-3 backdrop-blur-xl lg:hidden">
        <Logo />
        <button aria-label="Open menu" onClick={() => setOpen(true)} className="rounded-lg p-2 hover:bg-white/5"><Menu className="size-5" /></button>
      </header>

      <AnimatePresence>
        {open && (
          <motion.div className="fixed inset-0 z-40 lg:hidden" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <div className="absolute inset-0 bg-black/70" onClick={() => setOpen(false)} />
            <motion.aside className="absolute inset-y-0 left-0 w-72 border-r border-white/10 bg-ink-950"
              initial={{ x: -300 }} animate={{ x: 0 }} exit={{ x: -300 }} transition={{ type: 'spring', damping: 30, stiffness: 300 }}>
              <button aria-label="Close menu" onClick={() => setOpen(false)} className="absolute top-4 right-3 rounded-lg p-1.5 hover:bg-white/5"><X className="size-5" /></button>
              <Sidebar onNavigate={() => setOpen(false)} />
            </motion.aside>
          </motion.div>
        )}
      </AnimatePresence>

      <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
        <motion.div key={location.pathname.split('/')[1]} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.25 }}>
          <Outlet />
        </motion.div>
      </main>
    </div>
  )
}
