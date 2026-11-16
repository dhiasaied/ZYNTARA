import { motion } from 'framer-motion'
import { Network, Pencil, Play, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { CrewNetwork } from '../../components/CrewNetwork'
import { Button, ConfirmDialog, EmptyState, PageHeader, Pill, Skeleton } from '../../components/ui'
import { timeAgo } from '../../lib/format'
import { useCrews, useDeleteCrew } from '../../lib/queries'
import type { Crew } from '../../lib/types'

export default function Crews() {
  const { data, isLoading } = useCrews()
  const remove = useDeleteCrew()
  const navigate = useNavigate()
  const [toDelete, setToDelete] = useState<Crew | null>(null)

  return (
    <>
      <PageHeader eyebrow="Rung 6 · Agentic AI" title="Crews"
        subtitle="Several agents, each with its own role and tools, collaborating on one goal. A supervisor decides who acts next — or they work in sequence."
        actions={<Link to="/crews/new"><Button icon={<Plus className="size-4" />}>New crew</Button></Link>} />

      {isLoading ? (
        <div className="grid gap-4 md:grid-cols-2">{[0, 1].map((i) => <Skeleton key={i} className="h-80" />)}</div>
      ) : !data?.member.length ? (
        <EmptyState icon={<Network />} title="No crew yet" text="Combine at least two agents — for example a researcher, a coder and a reviewer — and give them a shared goal."
          action={<Link to="/crews/new"><Button icon={<Plus className="size-4" />}>Assemble a crew</Button></Link>} />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {data.member.map((crew, i) => (
            <motion.article key={crew.id} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }} className="glass flex flex-col p-5">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h3 className="truncate font-display text-lg font-bold">{crew.name}</h3>
                  <p className="line-clamp-2 text-sm text-sand-400">{crew.goal}</p>
                </div>
                <Pill className="shrink-0 capitalize">{crew.strategy}</Pill>
              </div>
              <div className="my-4 max-w-[240px] self-center">
                <CrewNetwork members={crew.members.map((m) => ({ role: m.role, name: m.agent.name }))} supervisor={crew.strategy === 'supervisor'} />
              </div>
              <div className="mt-auto flex items-center gap-2">
                <Button size="sm" onClick={() => navigate(`/crews/${crew.id}`)} icon={<Play className="size-3.5" />}>Run</Button>
                <Button size="sm" variant="secondary" onClick={() => navigate(`/crews/${crew.id}/edit`)} icon={<Pencil className="size-3.5" />}>Edit</Button>
                <span className="ml-auto text-[11px] text-sand-500">{crew.members.length} agents · {timeAgo(crew.updatedAt)}</span>
                <button aria-label={`Delete ${crew.name}`} onClick={() => setToDelete(crew)} className="rounded-lg p-1.5 text-sand-500 hover:bg-bad-500/10 hover:text-red-300"><Trash2 className="size-4" /></button>
              </div>
            </motion.article>
          ))}
        </div>
      )}

      <ConfirmDialog open={!!toDelete} onClose={() => setToDelete(null)} loading={remove.isPending} title={`Delete ${toDelete?.name}?`}
        text="The agents themselves are kept." onConfirm={async () => {
          if (!toDelete) return
          await remove.mutateAsync(toDelete.id)
          toast.success('Crew deleted')
          setToDelete(null)
        }} />
    </>
  )
}
