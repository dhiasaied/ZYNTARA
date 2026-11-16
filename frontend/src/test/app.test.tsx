import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { Ladder } from '../components/Ladder'
import { LevelBadge, StatusBadge } from '../components/ui'
import { ApiError, api, asCollection } from '../lib/api'
import { duration } from '../lib/format'
import { useAuth } from '../stores/auth'

const wrap = (ui: React.ReactNode) =>
  render(<QueryClientProvider client={new QueryClient()}><MemoryRouter>{ui}</MemoryRouter></QueryClientProvider>)

afterEach(() => {
  vi.restoreAllMocks()
  useAuth.getState().logout()
})

describe('ladder', () => {
  it('renders the six rungs from the video and counts the climbed ones', () => {
    wrap(<Ladder counts={[{ level: 'app', count: 2 }, { level: 'rag', count: 1 }, { level: 'agentic', count: 0 }]} />)
    for (const rung of ['PROMPT', 'APP', 'TOOLS', 'RAG', 'AGENT', 'AGENTIC']) expect(screen.getByText(rung)).toBeInTheDocument()
    expect(screen.getByText('2/6 rungs climbed')).toBeInTheDocument()
  })

  it('labels levels and statuses', () => {
    wrap(<><LevelBadge level="agent" /><StatusBadge status="running" /></>)
    expect(screen.getByText('AI Agent')).toBeInTheDocument()
    expect(screen.getByText('running')).toBeInTheDocument()
  })
})

describe('api client', () => {
  it('normalises API Platform collections with and without hydra prefix', () => {
    expect(asCollection({ member: [1, 2], totalItems: 7 })).toEqual({ member: [1, 2], totalItems: 7 })
    expect(asCollection({ 'hydra:member': [1], 'hydra:totalItems': 1 })).toEqual({ member: [1], totalItems: 1 })
  })

  it('sends the JWT and surfaces validation violations', async () => {
    useAuth.getState().setSession('jwt-123', { id: 'u' } as never)
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ violations: [{ propertyPath: 'name', message: 'This value should not be blank.' }] }), { status: 422 }),
    )
    const error = await api('POST', '/api/agents', { json: { name: '' } }).catch((e) => e)
    expect(fetchMock.mock.calls[0][1]?.headers).toMatchObject({ Authorization: 'Bearer jwt-123', 'Content-Type': 'application/json' })
    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).fieldErrors()).toEqual({ name: 'This value should not be blank.' })
  })

  it('uses merge-patch for PATCH', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('{}', { status: 200 }))
    await api('PATCH', '/api/agents/1', { json: { name: 'x' } })
    expect(fetchMock.mock.calls[0][1]?.headers).toMatchObject({ 'Content-Type': 'application/merge-patch+json' })
  })
})

describe('format', () => {
  it('formats durations', () => {
    expect(duration(450)).toBe('450 ms')
    expect(duration(2500)).toBe('2.5 s')
    expect(duration(null)).toBe('—')
  })
})
