import clsx from 'clsx'
import { AlertTriangle, Bell, CheckCheck, CheckCircle2, FileCheck2, FileX2, Info, Trash2 } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { useQueryClient } from '@tanstack/react-query'
import { Button, EmptyState, PageHeader, Skeleton, Tabs } from '../components/ui'
import { del } from '../lib/api'
import { timeAgo } from '../lib/format'
import { useMarkNotification, useNotifications, useReadAll } from '../lib/queries'
import type { Notification } from '../lib/types'

const ICONS: Record<Notification['type'], ReactNode> = {
  run_succeeded: <CheckCircle2 className="text-green-300" />,
  run_failed: <AlertTriangle className="text-red-300" />,
  document_indexed: <FileCheck2 className="text-sky-300" />,
  document_failed: <FileX2 className="text-red-300" />,
  system: <Info className="text-ember-300" />,
}

export default function Notifications() {
  const [filter, setFilter] = useState<'all' | 'unread'>('all')
  const { data, isLoading } = useNotifications(filter === 'unread')
  const mark = useMarkNotification()
  const readAll = useReadAll()
  const qc = useQueryClient()
  const navigate = useNavigate()

  return (
    <>
      <PageHeader eyebrow="Inbox" title="Notifications" subtitle="Finished and failed runs, indexed documents and account changes."
        actions={<>
          <Tabs value={filter} onChange={setFilter} items={[{ value: 'all', label: 'All' }, { value: 'unread', label: 'Unread' }]} />
          <Button variant="secondary" onClick={() => readAll.mutate()} loading={readAll.isPending} icon={<CheckCheck className="size-4" />}>Mark all read</Button>
        </>} />

      {isLoading ? <Skeleton className="h-80" /> : !data?.member.length ? (
        <EmptyState icon={<Bell />} title="All caught up" text="You will be notified here when background work finishes." />
      ) : (
        <ul className="glass divide-y divide-white/[0.05]">
          {data.member.map((n) => (
            <li key={n.id} className={clsx('group flex items-start gap-3 p-4 transition', !n.read && 'bg-ember-500/[0.03]')}>
              <span className="mt-0.5 [&_svg]:size-5">{ICONS[n.type]}</span>
              <button className="min-w-0 flex-1 text-left" onClick={() => {
                if (!n.read) mark.mutate({ id: n.id, read: true })
                if (n.link) navigate(n.link)
              }}>
                <div className="flex items-center gap-2 text-sm font-semibold">{n.title}{!n.read && <span className="size-1.5 rounded-full bg-ember-500" />}</div>
                {n.body && <p className="mt-0.5 line-clamp-2 text-sm text-sand-400">{n.body}</p>}
                <div className="mt-1 text-[11px] text-sand-500">{timeAgo(n.createdAt)}</div>
              </button>
              <button className="rounded-lg px-2 py-1 text-xs text-sand-400 opacity-0 transition group-hover:opacity-100 hover:bg-white/5" onClick={() => mark.mutate({ id: n.id, read: !n.read })}>
                {n.read ? 'Mark unread' : 'Mark read'}
              </button>
              <button aria-label="Delete notification" className="rounded-lg p-1.5 text-sand-500 opacity-0 transition group-hover:opacity-100 hover:text-red-300"
                onClick={async () => { await del(`/api/notifications/${n.id}`); qc.invalidateQueries({ queryKey: ['notifications'] }) }}>
                <Trash2 className="size-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </>
  )
}
