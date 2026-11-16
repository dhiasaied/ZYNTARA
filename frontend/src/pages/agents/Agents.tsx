import { motion } from 'framer-motion'
import { Bot, BookOpenText, Brain, MessageSquare, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { Button, ChipIcon, ConfirmDialog, EmptyState, Input, LevelBadge, PageHeader, Pill, Skeleton } from '../../components/ui'
import { timeAgo, VERTICALS } from '../../lib/format'
import { useAgents, useDeleteAgent, useTools } from '../../lib/queries'
import type { Agent } from '../../lib/types'

export default function Agents() {
  const [search, setSearch] = useState('')
  const { data, isLoading } = useAgents(search)
  const { data: tools = [] } = useTools()
  const remove = useDeleteAgent()
  const [toDelete, setToDelete] = useState<Agent | null>(null)
  const navigate = useNavigate()
  const toolName = (k: string) => tools.find((t) => t.key === k)?.name ?? k

  return (
    <>
      <PageHeader
        eyebrow="Rungs 2–5 · App → Tools → RAG → Agent"
        title="Agents"
        subtitle="An AI app is an LLM with a purpose. Give it tools, documents and autonomy and it climbs the ladder — the badge shows where each one stands."
        actions={<Link to="/agents/new"><Button icon={<Plus className="size-4" />}>New agent</Button></Link>}
      />

      <div className="relative mb-5 max-w-sm">
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-sand-500" />
        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search agents…" className="pl-9" aria-label="Search agents" />
      </div>

      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-52" />)}</div>
      ) : !data?.member.length ? (
        <EmptyState icon={<Bot />} title={search ? 'No agent matches' : 'No agents yet'}
          text="Start from a template — an email assistant, a shop support agent or an autonomous researcher — or from a blank prompt."
          action={<Link to="/agents/new"><Button icon={<Plus className="size-4" />}>Create your first agent</Button></Link>} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {data.member.map((agent, i) => (
            <motion.article key={agent.id} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.04 }}
              className="glass group flex flex-col p-5 transition hover:border-ember-500/30">
              <div className="flex items-start gap-3">
                <ChipIcon size="sm" active={agent.autonomous}>{agent.autonomous ? <Brain /> : <Bot />}</ChipIcon>
                <div className="min-w-0 flex-1">
                  <h3 className="truncate font-display font-bold">{agent.name}</h3>
                  <div className="mt-1 flex flex-wrap items-center gap-1.5"><LevelBadge level={agent.level} /><Pill>{VERTICALS[agent.vertical] ?? agent.vertical}</Pill></div>
                </div>
              </div>
              <p className="mt-3 line-clamp-2 min-h-10 text-sm text-sand-400">{agent.description || agent.systemPrompt}</p>
              <div className="mt-3 flex flex-wrap gap-1.5">
                {agent.tools.map((t) => <Pill key={t}>{toolName(t)}</Pill>)}
                {agent.knowledgeBases.map((kb) => <Pill key={kb.id} className="border-sky-400/20 text-sky-200"><BookOpenText className="size-3" />{kb.name}</Pill>)}
                {agent.memoryEnabled && <Pill className="border-violet-400/20 text-violet-200">memory</Pill>}
              </div>
              <div className="mt-auto flex items-center gap-2 pt-5">
                <Button size="sm" onClick={() => navigate(`/agents/${agent.id}`)} icon={<MessageSquare className="size-3.5" />}>Chat</Button>
                <Button size="sm" variant="secondary" onClick={() => navigate(`/agents/${agent.id}/edit`)} icon={<Pencil className="size-3.5" />}>Edit</Button>
                <span className="ml-auto text-[11px] text-sand-500">{agent.provider} · {timeAgo(agent.updatedAt)}</span>
                <button aria-label={`Delete ${agent.name}`} onClick={() => setToDelete(agent)} className="rounded-lg p-1.5 text-sand-500 hover:bg-bad-500/10 hover:text-red-300"><Trash2 className="size-4" /></button>
              </div>
            </motion.article>
          ))}
        </div>
      )}

      <ConfirmDialog open={!!toDelete} onClose={() => setToDelete(null)} loading={remove.isPending}
        title={`Delete ${toDelete?.name}?`} text="Its conversations are deleted too, and it is removed from every crew. Past runs stay in the history."
        onConfirm={async () => {
          if (!toDelete) return
          await remove.mutateAsync(toDelete.id)
          toast.success('Agent deleted')
          setToDelete(null)
        }} />
    </>
  )
}
