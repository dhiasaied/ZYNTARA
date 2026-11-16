import { useQueryClient } from '@tanstack/react-query'
import clsx from 'clsx'
import { motion } from 'framer-motion'
import { ArrowUp, Bot, ChevronDown, MessageSquarePlus, Pencil, Square, Trash2, User as UserIcon } from 'lucide-react'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { Markdown } from '../../components/Markdown'
import { RunTimeline } from '../../components/RunTimeline'
import { Button, ChipIcon, LevelBadge, Pill, Skeleton, Textarea } from '../../components/ui'
import { del, post } from '../../lib/api'
import { timeAgo } from '../../lib/format'
import { useAgent, useConversation, useConversations } from '../../lib/queries'
import type { Conversation, Message } from '../../lib/types'
import { useLiveRun } from '../../lib/useLiveRun'

function PendingReply({ runId, onDone }: { runId: string; onDone: () => void }) {
  const { run, steps, live } = useLiveRun(runId)
  const [expanded, setExpanded] = useState(true)
  const active = !run || run.status === 'queued' || run.status === 'running'
  useEffect(() => {
    if (run && !active) onDone()
  }, [run, active, onDone])

  return (
    <div className="flex gap-3">
      <ChipIcon size="sm" active><Bot /></ChipIcon>
      <div className="min-w-0 flex-1 rounded-2xl rounded-tl-sm border border-ember-500/20 bg-ember-500/[0.03] p-3">
        <button onClick={() => setExpanded(!expanded)} className="flex w-full items-center gap-2 text-xs font-semibold text-ember-300">
          <span className="size-1.5 animate-pulse rounded-full bg-ember-400" />
          {run?.status === 'queued' ? 'Queued…' : 'Working'} · {steps.length} step{steps.length === 1 ? '' : 's'}
          <ChevronDown className={clsx('ml-auto size-4 transition', expanded && 'rotate-180')} />
        </button>
        {expanded && <div className="mt-3"><RunTimeline steps={steps.filter((s) => s.type !== 'final')} live={live} running={active} /></div>}
      </div>
    </div>
  )
}

function Bubble({ message }: { message: Message }) {
  const mine = message.role === 'user'
  return (
    <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className={clsx('flex gap-3', mine && 'flex-row-reverse')}>
      {mine ? <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-white/[0.06]"><UserIcon className="size-4 text-sand-200" /></div> : <ChipIcon size="sm"><Bot /></ChipIcon>}
      <div className={clsx('max-w-[85%] rounded-2xl p-3.5', mine ? 'rounded-tr-sm bg-gradient-to-b from-ember-500/90 to-ember-600/90 text-ink-950' : 'rounded-tl-sm border border-white/[0.07] bg-ink-850/80')}>
        {mine ? <p className="text-sm font-medium whitespace-pre-wrap">{message.content}</p> : <Markdown>{message.content}</Markdown>}
        <div className={clsx('mt-1.5 flex gap-2 text-[10px]', mine ? 'text-ink-900/70' : 'text-sand-500')}>
          {timeAgo(message.createdAt)}
          {!mine && message.runId && <Link to={`/runs/${message.runId}`} className="hover:underline">view reasoning →</Link>}
        </div>
      </div>
    </motion.div>
  )
}

export default function AgentChat() {
  const { id, conversationId } = useParams()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { data: agent, isLoading } = useAgent(id)
  const { data: conversations } = useConversations(id)
  const { data: conversation } = useConversation(conversationId)
  const [text, setText] = useState('')
  const [sending, setSending] = useState(false)
  const [optimistic, setOptimistic] = useState<Message | null>(null)
  const bottom = useRef<HTMLDivElement>(null)

  const messages = conversation?.messages ?? []
  const pending = [...messages].reverse().find((m) => m.role === 'user' && (m.runStatus === 'queued' || m.runStatus === 'running'))
  const shown = optimistic && !messages.some((m) => m.id === optimistic.id) ? [...messages, optimistic] : messages
  const pendingRunId = pending?.runId ?? (optimistic?.runId || undefined)

  useEffect(() => {
    bottom.current?.scrollIntoView({ behavior: 'smooth' })
  }, [shown.length, pendingRunId])

  const send = async (e?: FormEvent) => {
    e?.preventDefault()
    const content = text.trim()
    if (!content || sending || !agent) return
    setSending(true)
    try {
      let convId = conversationId
      if (!convId) {
        const created = await post<Conversation>('/api/conversations', { agent: agent['@id'] })
        convId = created.id
      }
      const res = await post<{ message: Message; title: string }>(`/api/conversations/${convId}/messages`, { content })
      // Seed the thread cache so the message stays visible while the URL switches to the new conversation.
      qc.setQueryData<Conversation>(['conversation', convId], (old) => ({
        ...(old ?? { '@id': `/api/conversations/${convId}`, id: convId!, agent: { '@id': agent['@id'], id: agent.id, name: agent.name }, messageCount: 0, lastMessagePreview: null, createdAt: res.message.createdAt, updatedAt: res.message.createdAt }),
        title: res.title,
        messages: [...(old?.messages ?? []), res.message],
      }))
      setOptimistic(res.message)
      setText('')
      qc.invalidateQueries({ queryKey: ['conversations', id] })
      if (convId !== conversationId) navigate(`/agents/${id}/chat/${convId}`, { replace: true })
    } finally {
      setSending(false)
    }
  }

  const onRunDone = () => {
    setOptimistic(null)
    qc.invalidateQueries({ queryKey: ['conversation', conversationId] })
    qc.invalidateQueries({ queryKey: ['conversations', id] })
  }

  if (isLoading) return <Skeleton className="h-[70vh]" />
  if (!agent) return <p className="text-sand-400">Agent not found.</p>

  return (
    <div className="grid h-[calc(100vh-7rem)] gap-4 lg:h-[calc(100vh-5rem)] lg:grid-cols-[260px_1fr]">
      <aside className="glass hidden flex-col overflow-hidden lg:flex">
        <div className="border-b border-white/[0.06] p-3">
          <Button className="w-full" variant="secondary" icon={<MessageSquarePlus className="size-4" />} onClick={() => { setOptimistic(null); navigate(`/agents/${id}`) }}>New chat</Button>
        </div>
        <ul className="flex-1 space-y-0.5 overflow-y-auto p-2">
          {conversations?.member.map((c) => (
            <li key={c.id} className="group relative">
              <Link to={`/agents/${id}/chat/${c.id}`} className={clsx('block rounded-lg p-2.5 pr-8 transition', c.id === conversationId ? 'bg-ember-500/10' : 'hover:bg-white/[0.04]')}>
                <div className="truncate text-sm font-medium">{c.title}</div>
                <div className="text-[11px] text-sand-500">{c.messageCount} messages · {timeAgo(c.updatedAt)}</div>
              </Link>
              <button aria-label="Delete conversation" onClick={async () => {
                await del(`/api/conversations/${c.id}`)
                qc.invalidateQueries({ queryKey: ['conversations', id] })
                if (c.id === conversationId) navigate(`/agents/${id}`)
              }} className="absolute top-2.5 right-2 hidden rounded p-1 text-sand-500 group-hover:block hover:text-red-300"><Trash2 className="size-3.5" /></button>
            </li>
          ))}
          {!conversations?.member.length && <li className="p-3 text-xs text-sand-500">No conversations yet.</li>}
        </ul>
      </aside>

      <section className="glass flex min-h-0 flex-col">
        <header className="flex items-center gap-3 border-b border-white/[0.06] p-4">
          <ChipIcon size="sm" active={agent.autonomous}><Bot /></ChipIcon>
          <div className="min-w-0 flex-1">
            <h1 className="truncate font-display font-bold">{agent.name}</h1>
            <div className="mt-0.5 flex flex-wrap items-center gap-1.5"><LevelBadge level={agent.level} /><Pill>{agent.provider} · {agent.model}</Pill></div>
          </div>
          <Link to={`/agents/${agent.id}/edit`}><Button size="sm" variant="secondary" icon={<Pencil className="size-3.5" />}>Edit</Button></Link>
        </header>

        <div className="flex-1 space-y-5 overflow-y-auto p-4 sm:p-6">
          {!shown.length && (
            <div className="flex h-full flex-col items-center justify-center text-center">
              <ChipIcon size="lg" active><Bot /></ChipIcon>
              <h2 className="mt-5 font-display text-xl font-bold">Talk to {agent.name}</h2>
              <p className="mt-1 max-w-md text-sm text-sand-400">{agent.description || 'Ask it something within its purpose.'}</p>
              {agent.tools.length > 0 && <p className="mt-3 text-xs text-sand-500">Can use: {agent.tools.join(', ')}{agent.knowledgeBases.length ? `, documents from ${agent.knowledgeBases.map((k) => k.name).join(', ')}` : ''}</p>}
            </div>
          )}
          {shown.map((m) => <Bubble key={m.id} message={m} />)}
          {pendingRunId && <PendingReply key={pendingRunId} runId={pendingRunId} onDone={onRunDone} />}
          <div ref={bottom} />
        </div>

        <form onSubmit={send} className="flex items-end gap-2 border-t border-white/[0.06] p-3">
          <Textarea value={text} onChange={(e) => setText(e.target.value)} rows={1} placeholder={`Message ${agent.name}…`} className="min-h-11 bg-ink-950/40"
            onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void send() } }} aria-label="Message" />
          {pendingRunId ? (
            <Button type="button" variant="secondary" aria-label="Stop" onClick={() => post(`/api/runs/${pendingRunId}/cancel`)} icon={<Square className="size-4" />} />
          ) : (
            <Button type="submit" aria-label="Send" loading={sending} disabled={!text.trim()} icon={<ArrowUp className="size-4" />} />
          )}
        </form>
      </section>
    </div>
  )
}
