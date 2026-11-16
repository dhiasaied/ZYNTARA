import { motion } from 'framer-motion'
import { BookOpenText, FileText, Plus, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { Button, ChipIcon, ConfirmDialog, EmptyState, Field, Input, Modal, PageHeader, Skeleton, Textarea } from '../../components/ui'
import { del } from '../../lib/api'
import { bytes, timeAgo } from '../../lib/format'
import { useKnowledgeBases, useSaveKnowledgeBase } from '../../lib/queries'
import type { KnowledgeBase } from '../../lib/types'
import { useQueryClient } from '@tanstack/react-query'

export default function Knowledge() {
  const { data, isLoading } = useKnowledgeBases()
  const save = useSaveKnowledgeBase()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [toDelete, setToDelete] = useState<KnowledgeBase | null>(null)
  const [deleting, setDeleting] = useState(false)

  const create = async (e: FormEvent) => {
    e.preventDefault()
    const kb = await save.mutateAsync({ data: { name, description: description || null } })
    setOpen(false)
    setName('')
    setDescription('')
    navigate(`/knowledge/${kb.id}`)
  }

  return (
    <>
      <PageHeader eyebrow="Rung 4 · RAG" title="Knowledge"
        subtitle="Give your agents documents to read. They retrieve the most relevant passages and answer only from them — with sources."
        actions={<Button onClick={() => setOpen(true)} icon={<Plus className="size-4" />}>New knowledge base</Button>} />

      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-40" />)}</div>
      ) : !data?.member.length ? (
        <EmptyState icon={<BookOpenText />} title="No knowledge base yet" text="Create one, drop your PDFs, policies or course notes in it, then attach it to an agent."
          action={<Button onClick={() => setOpen(true)} icon={<Plus className="size-4" />}>Create a knowledge base</Button>} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {data.member.map((kb, i) => (
            <motion.div key={kb.id} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.04 }} className="glass group relative p-5 transition hover:border-sky-400/30">
              <Link to={`/knowledge/${kb.id}`} className="absolute inset-0" aria-label={`Open ${kb.name}`} />
              <div className="flex items-start gap-3">
                <ChipIcon size="sm"><FileText className="text-sky-300" /></ChipIcon>
                <div className="min-w-0 flex-1">
                  <h3 className="truncate font-display font-bold">{kb.name}</h3>
                  <p className="text-xs text-sand-500">Created {timeAgo(kb.createdAt)}</p>
                </div>
                <button aria-label={`Delete ${kb.name}`} onClick={() => setToDelete(kb)} className="relative z-10 rounded-lg p-1.5 text-sand-500 hover:bg-bad-500/10 hover:text-red-300"><Trash2 className="size-4" /></button>
              </div>
              <p className="mt-3 line-clamp-2 min-h-10 text-sm text-sand-400">{kb.description || 'No description.'}</p>
              <div className="mt-4 grid grid-cols-3 gap-2 text-center">
                {[['Docs', `${kb.indexedCount}/${kb.documentCount}`], ['Chunks', kb.chunkCount], ['Size', bytes(kb.totalBytes)]].map(([l, v]) => (
                  <div key={l} className="rounded-lg bg-white/[0.03] py-2"><div className="font-display text-sm font-bold">{v}</div><div className="text-[10px] text-sand-500 uppercase">{l}</div></div>
                ))}
              </div>
            </motion.div>
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="New knowledge base">
        <form onSubmit={create} className="space-y-4">
          <Field label="Name"><Input required value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Shop policies" autoFocus /></Field>
          <Field label="Description"><Textarea rows={3} value={description} onChange={(e) => setDescription(e.target.value)} placeholder="What documents live here?" /></Field>
          <div className="flex justify-end gap-2"><Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancel</Button><Button type="submit" loading={save.isPending}>Create</Button></div>
        </form>
      </Modal>

      <ConfirmDialog open={!!toDelete} onClose={() => setToDelete(null)} loading={deleting} title={`Delete ${toDelete?.name}?`}
        text="All its documents and their embeddings are deleted. Agents using it lose access."
        onConfirm={async () => {
          if (!toDelete) return
          setDeleting(true)
          try {
            await del(`/api/knowledge_bases/${toDelete.id}`)
            qc.invalidateQueries({ queryKey: ['kbs'] })
            toast.success('Knowledge base deleted')
            setToDelete(null)
          } finally {
            setDeleting(false)
          }
        }} />
    </>
  )
}
