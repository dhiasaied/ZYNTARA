import { fetchEventSource } from '@microsoft/fetch-event-source'
import { useEffect, useRef } from 'react'
import { create } from 'zustand'
import { get } from './api'

/**
 * One private Mercure connection per session, subscribed to the user's own topics
 * (`…/users/{id}` and `…/users/{id}/runs/{runId}`). Events are fanned out in-process.
 */
type Handler = (data: Record<string, unknown>) => void
const handlers = new Map<string, Set<Handler>>()

export function publishLocal(event: string, data: Record<string, unknown>): void {
  handlers.get(event)?.forEach((h) => h(data))
}

export function useRealtimeEvent(event: string, handler: Handler): void {
  const ref = useRef(handler)
  ref.current = handler
  useEffect(() => {
    const fn: Handler = (d) => ref.current(d)
    if (!handlers.has(event)) handlers.set(event, new Set())
    handlers.get(event)!.add(fn)
    return () => {
      handlers.get(event)?.delete(fn)
    }
  }, [event])
}

interface RealtimeState {
  status: 'off' | 'connecting' | 'live' | 'error'
  set: (s: RealtimeState['status']) => void
}
export const useRealtimeStatus = create<RealtimeState>((set) => ({ status: 'off', set: (status) => set({ status }) }))

interface TokenResponse { enabled: boolean; hubUrl?: string; token?: string; topics?: string[]; expiresIn?: number }

class RetriableError extends Error {}
class FatalError extends Error {}

export function connectRealtime(): () => void {
  const controller = new AbortController()
  const setStatus = useRealtimeStatus.getState().set
  let refreshTimer: number | undefined

  const start = async () => {
    let config: TokenResponse
    try {
      config = await get<TokenResponse>('/api/realtime/token')
    } catch {
      setStatus('error')
      refreshTimer = window.setTimeout(start, 15000)
      return
    }
    if (!config.enabled || !config.hubUrl || !config.token || !config.topics) {
      setStatus('off')
      return
    }
    const url = new URL(config.hubUrl, window.location.origin)
    config.topics.forEach((t) => url.searchParams.append('topic', t))
    setStatus('connecting')
    // Renew the subscriber token shortly before it expires.
    refreshTimer = window.setTimeout(() => {
      inner.abort()
      start()
    }, Math.max(60, (config.expiresIn ?? 3600) - 120) * 1000)

    const inner = new AbortController()
    controller.signal.addEventListener('abort', () => inner.abort())
    fetchEventSource(url.toString(), {
      headers: { Authorization: `Bearer ${config.token}` },
      signal: inner.signal,
      openWhenHidden: true,
      async onopen(response) {
        if (response.ok) {
          setStatus('live')
          return
        }
        if (response.status === 401 || response.status === 403) throw new FatalError('unauthorised')
        throw new RetriableError(String(response.status))
      },
      onmessage(message) {
        try {
          const parsed = JSON.parse(message.data) as { event: string; data: Record<string, unknown> }
          publishLocal(parsed.event, parsed.data)
        } catch {
          /* ignore malformed */
        }
      },
      onerror(err) {
        setStatus('error')
        if (err instanceof FatalError) throw err
        return 5000
      },
    }).catch(() => setStatus('error'))
  }

  start()
  return () => {
    controller.abort()
    window.clearTimeout(refreshTimer)
    setStatus('off')
  }
}
