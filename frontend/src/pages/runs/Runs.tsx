import { Activity, ChevronLeft, ChevronRight } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button, EmptyState, PageHeader, Pill, Select, Skeleton, StatusBadge } from '../../components/ui'
import { duration, timeAgo } from '../../lib/format'
import { useRuns } from '../../lib/queries'

const PER_PAGE = 20

export default function Runs() {
  const [status, setStatus] = useState('')
  const [kind, setKind] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useRuns({ status: status || undefined, kind: kind || undefined, page, itemsPerPage: PER_PAGE })
  const pages = Math.max(1, Math.ceil((data?.totalItems ?? 0) / PER_PAGE))

  return (
    <>
      <PageHeader eyebrow="History" title="Runs" subtitle="Every prompt, agent answer and crew execution — with its full reasoning trace." />

      <div className="mb-4 flex flex-wrap gap-2">
        <Select aria-label="Filter by status" className="w-40" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
          <option value="">All statuses</option>
          {['queued', 'running', 'succeeded', 'failed', 'cancelled'].map((s) => <option key={s} value={s}>{s}</option>)}
        </Select>
        <Select aria-label="Filter by type" className="w-40" value={kind} onChange={(e) => { setKind(e.target.value); setPage(1) }}>
          <option value="">All types</option>
          <option value="prompt">Prompt</option>
          <option value="agent">Agent</option>
          <option value="crew">Crew</option>
        </Select>
      </div>

      {isLoading ? <Skeleton className="h-96" /> : !data?.member.length ? (
        <EmptyState icon={<Activity />} title="No runs" text="Runs appear here as soon as you send a prompt, chat with an agent or launch a crew." />
      ) : (
        <div className="glass overflow-x-auto">
          <table className="w-full min-w-[720px] text-sm">
            <thead>
              <tr className="border-b border-white/[0.06] text-left text-xs text-sand-500 uppercase">
                <th className="px-4 py-3 font-semibold">Run</th><th className="px-4 py-3 font-semibold">Type</th><th className="px-4 py-3 font-semibold">Status</th>
                <th className="px-4 py-3 text-right font-semibold">Steps</th><th className="px-4 py-3 text-right font-semibold">Tokens</th><th className="px-4 py-3 text-right font-semibold">Duration</th><th className="px-4 py-3 text-right font-semibold">When</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/[0.04]">
              {data.member.map((r) => (
                <tr key={r.id} className="transition hover:bg-white/[0.02]">
                  <td className="max-w-xs px-4 py-3">
                    <Link to={`/runs/${r.id}`} className="block">
                      <span className="block truncate font-semibold hover:text-ember-300">{r.title}</span>
                      <span className="block truncate text-xs text-sand-500">{r.input}</span>
                    </Link>
                  </td>
                  <td className="px-4 py-3"><Pill className="capitalize">{r.kind}</Pill></td>
                  <td className="px-4 py-3"><StatusBadge status={r.status} /></td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.stepCount}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.tokensIn + r.tokensOut}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{duration(r.durationMs)}</td>
                  <td className="px-4 py-3 text-right text-xs text-sand-500">{timeAgo(r.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="flex items-center justify-between border-t border-white/[0.06] px-4 py-3 text-xs text-sand-400">
            <span>{data.totalItems} runs</span>
            <div className="flex items-center gap-2">
              <Button size="sm" variant="ghost" disabled={page <= 1} onClick={() => setPage(page - 1)} aria-label="Previous page" icon={<ChevronLeft className="size-4" />} />
              <span>Page {page} / {pages}</span>
              <Button size="sm" variant="ghost" disabled={page >= pages} onClick={() => setPage(page + 1)} aria-label="Next page" icon={<ChevronRight className="size-4" />} />
            </div>
          </div>
        </div>
      )}
    </>
  )
}
