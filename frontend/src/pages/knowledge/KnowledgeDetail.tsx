import { useQueryClient } from '@tanstack/react-query'
import clsx from 'clsx'
import { AnimatePresence, motion } from 'framer-motion'
import { ArrowLeft, FileText, RefreshCw, Search, Trash2, UploadCloud } from 'lucide-react'
import { useRef, useState, type DragEvent, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { toast } from 'sonner'
import { Button, Card, Input, PageHeader, Skeleton } from '../../components/ui'
import { api, ApiError, del, post } from '../../lib/api'
import { bytes, timeAgo } from '../../lib/format'
import { useDocuments, useKnowledgeBase } from '../../lib/queries'
import type { DocumentItem, DocumentStatus } from '../../lib/types'

const STATUS: Record<DocumentStatus, string> = {
  pending: 'text-sand-400 bg-white/5',
  processing: 'text-ember-300 bg-ember-500/15',
  indexed: 'text-green-300 bg-ok-500/10',
  failed: 'text-red-300 bg-bad-500/10',
}
const ACCEPT = '.pdf,.txt,.md,.markdown,.csv,.html,.htm,.json'

interface Hit { content: string; score: number; filename: string; chunk_index: number }

export default function KnowledgeDetail() {
  const { id } = useParams()
  const qc = useQueryClient()
  const { data: kb, isLoading } = useKnowledgeBase(id)
  const { data: docs } = useDocuments(id)
  const [dragging, setDragging] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [query, setQuery] = useState('')
  const [hits, setHits] = useState<Hit[] | null>(null)
  const [searching, setSearching] = useState(false)
  const input = useRef<HTMLInputElement>(null)

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['documents', id] })
    qc.invalidateQueries({ queryKey: ['kb', id] })
    qc.invalidateQueries({ queryKey: ['kbs'] })
  }

  const upload = async (files: FileList | File[]) => {
    const list = Array.from(files)
    if (!list.length) return
    const form = new FormData()
    list.forEach((f) => form.append('files[]', f))
    setUploading(true)
    try {
      const res = await api<{ documents: DocumentItem[]; errors: { file: string; error: string }[] }>('POST', `/api/knowledge_bases/${id}/documents`, { form })
      if (res.documents.length) toast.success(`${res.documents.length} file(s) uploaded — indexing in the background`)
      res.errors.forEach((e) => toast.error(`${e.file}: ${e.error}`))
      refresh()
    } catch (err) {
      if (err instanceof ApiError) toast.error(err.message)
    } finally {
      setUploading(false)
      if (input.current) input.current.value = ''
    }
  }

  const onDrop = (e: DragEvent) => {
    e.preventDefault()
    setDragging(false)
    void upload(e.dataTransfer.files)
  }

  const search = async (e: FormEvent) => {
    e.preventDefault()
    if (!query.trim()) return
    setSearching(true)
    try {
      setHits((await post<{ results: Hit[] }>(`/api/knowledge_bases/${id}/search`, { query, k: 5 })).results)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Search failed')
    } finally {
      setSearching(false)
    }
  }

  if (isLoading) return <Skeleton className="h-96" />
  if (!kb) return <p className="text-sand-400">Knowledge base not found.</p>

  return (
    <>
      <Link to="/knowledge" className="mb-4 inline-flex items-center gap-1 text-sm text-sand-400 hover:text-sand-50"><ArrowLeft className="size-4" />Knowledge</Link>
      <PageHeader eyebrow="Knowledge base" title={kb.name} subtitle={kb.description ?? undefined} />

      <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
        <div className="space-y-4">
          <div onDragOver={(e) => { e.preventDefault(); setDragging(true) }} onDragLeave={() => setDragging(false)} onDrop={onDrop}
            onClick={() => input.current?.click()} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && input.current?.click()}
            className={clsx('glass flex cursor-pointer flex-col items-center border-2 border-dashed p-10 text-center transition', dragging ? 'border-ember-500 bg-ember-500/5' : 'border-white/10 hover:border-ember-500/40')}>
            <motion.div animate={{ y: dragging ? -6 : 0 }} className="tile grid size-14 place-items-center text-ember-400">
              {uploading ? <RefreshCw className="size-6 animate-spin" /> : <UploadCloud className="size-6" />}
            </motion.div>
            <p className="mt-4 font-semibold">Drop documents here or click to browse</p>
            <p className="mt-1 text-xs text-sand-500">PDF, TXT, Markdown, CSV, HTML, JSON · up to 20 MB each · 10 files at once</p>
            <input ref={input} type="file" multiple accept={ACCEPT} className="hidden" onChange={(e) => e.target.files && upload(e.target.files)} />
          </div>

          <Card className="p-0">
            <div className="flex items-center justify-between border-b border-white/[0.06] px-5 py-3">
              <h2 className="font-display text-sm font-bold">Documents</h2>
              <span className="text-xs text-sand-500">{kb.indexedCount}/{kb.documentCount} indexed · {kb.chunkCount} chunks</span>
            </div>
            <ul className="divide-y divide-white/[0.05]">
              <AnimatePresence initial={false}>
                {docs?.member.map((d) => (
                  <motion.li key={d.id} layout initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="flex items-center gap-3 px-5 py-3">
                    <FileText className="size-5 shrink-0 text-sand-500" />
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-sm font-medium">{d.originalName}</div>
                      <div className="text-[11px] text-sand-500">{bytes(d.size)} · {d.chunkCount} chunks · {timeAgo(d.createdAt)}</div>
                      {d.error && <div className="mt-0.5 text-[11px] text-red-300">{d.error}</div>}
                    </div>
                    <span className={clsx('rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize', STATUS[d.status])}>
                      {d.status === 'processing' && <span className="mr-1 inline-block size-1.5 animate-pulse rounded-full bg-current" />}{d.status}
                    </span>
                    <button aria-label="Re-index" title="Re-index" onClick={async () => { await post(`/api/documents/${d.id}/reindex`); toast.success('Re-indexing queued'); refresh() }}
                      className="rounded-lg p-1.5 text-sand-500 hover:bg-white/5 hover:text-sand-50"><RefreshCw className="size-4" /></button>
                    <button aria-label="Delete document" title="Delete" onClick={async () => { await del(`/api/documents/${d.id}`); toast.success('Document deleted'); refresh() }}
                      className="rounded-lg p-1.5 text-sand-500 hover:bg-bad-500/10 hover:text-red-300"><Trash2 className="size-4" /></button>
                  </motion.li>
                ))}
              </AnimatePresence>
              {!docs?.member.length && <li className="px-5 py-8 text-center text-sm text-sand-500">No documents yet.</li>}
            </ul>
          </Card>
        </div>

        <Card className="h-fit lg:sticky lg:top-8">
          <h2 className="font-display font-bold">Test retrieval</h2>
          <p className="mt-1 mb-4 text-xs text-sand-400">See exactly which passages an agent would read before answering this question.</p>
          <form onSubmit={search} className="flex gap-2">
            <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="e.g. How long do refunds take?" aria-label="Question" />
            <Button type="submit" loading={searching} aria-label="Search" icon={<Search className="size-4" />} />
          </form>
          <div className="mt-4 space-y-2">
            {hits?.map((h, i) => (
              <motion.div key={i} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }} className="rounded-xl border border-white/[0.06] bg-ink-950/60 p-3">
                <div className="mb-1 flex justify-between font-mono text-[11px] text-sky-300"><span className="truncate">{h.filename} #{h.chunk_index + 1}</span><span>{h.score.toFixed(3)}</span></div>
                <div className="mb-2 h-1 overflow-hidden rounded-full bg-white/5"><div className="h-full bg-sky-400/70" style={{ width: `${Math.max(4, h.score * 100)}%` }} /></div>
                <p className="line-clamp-5 text-xs text-sand-200">{h.content}</p>
              </motion.div>
            ))}
            {hits && !hits.length && <p className="text-xs text-sand-500">Nothing relevant found. Upload documents or rephrase.</p>}
          </div>
        </Card>
      </div>
    </>
  )
}
