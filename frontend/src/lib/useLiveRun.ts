import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useRun } from './queries'
import { useRealtimeEvent } from './realtime'
import type { Run, RunStep } from './types'

/**
 * A run kept live by Mercure events (steps, streamed tokens, status), with React Query
 * polling as a safety net when realtime is unavailable.
 */
export function useLiveRun(runId?: string) {
  const qc = useQueryClient()
  const query = useRun(runId)
  const [live, setLive] = useState<{ actor: string; text: string } | null>(null)

  useRealtimeEvent('run.step', (data) => {
    if (data.runId !== runId) return
    const step = data as unknown as RunStep
    qc.setQueryData<Run>(['run', runId], (old) => {
      if (!old) return old
      const steps = old.steps ?? []
      if (steps.some((s) => s.id === step.id)) return old
      return { ...old, steps: [...steps, step].sort((a, b) => a.sequence - b.sequence), stepCount: steps.length + 1 }
    })
    // Text of a finished step supersedes the streamed preview.
    setLive((prev) => (prev && prev.actor === step.actor ? null : prev))
  })

  useRealtimeEvent('run.token', (data) => {
    if (data.runId !== runId) return
    const actor = String(data.actor)
    const delta = String(data.delta ?? '')
    setLive((prev) => (prev && prev.actor === actor ? { actor, text: prev.text + delta } : { actor, text: delta }))
  })

  useRealtimeEvent('run.status', (data) => {
    if (data.id !== runId) return
    qc.setQueryData<Run>(['run', runId], (old) => (old ? { ...old, ...(data as Partial<Run>) } : old))
    if (['succeeded', 'failed', 'cancelled'].includes(String(data.status))) {
      setLive(null)
      qc.invalidateQueries({ queryKey: ['run', runId] })
    }
  })

  return { ...query, run: query.data, steps: query.data?.steps ?? [], live }
}
