import { useAuth } from '../stores/auth'
import type { Collection } from './types'

const BASE = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '') ?? ''

export interface Violation { propertyPath: string; message: string }

export class ApiError extends Error {
  status: number
  violations: Violation[]

  constructor(message: string, status: number, violations: Violation[] = []) {
    super(message)
    this.status = status
    this.violations = violations
  }

  /** Violation messages keyed by field, for inline form errors. */
  fieldErrors(): Record<string, string> {
    return Object.fromEntries(this.violations.map((v) => [v.propertyPath, v.message]))
  }
}

interface RequestOptions {
  json?: unknown
  form?: FormData
  query?: Record<string, string | number | boolean | undefined | null>
  signal?: AbortSignal
  contentType?: string
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
  const url = path.startsWith('http') ? path : `${BASE}${path}`
  if (!query) return url
  const params = new URLSearchParams()
  Object.entries(query).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') params.set(k, String(v))
  })
  const qs = params.toString()
  return qs ? `${url}${url.includes('?') ? '&' : '?'}${qs}` : url
}

async function parseError(response: Response): Promise<ApiError> {
  let body: Record<string, unknown> = {}
  try {
    body = await response.json()
  } catch {
    /* non-JSON error */
  }
  const violations = (Array.isArray(body.violations) ? body.violations : []) as Violation[]
  const message =
    (typeof body.error === 'string' && body.error) ||
    (typeof body.message === 'string' && body.message) ||
    (violations[0] && `${violations[0].propertyPath ? violations[0].propertyPath + ': ' : ''}${violations[0].message}`) ||
    (typeof body.detail === 'string' && body.detail) ||
    (typeof body['hydra:description'] === 'string' && (body['hydra:description'] as string)) ||
    response.statusText ||
    'Request failed'
  return new ApiError(message, response.status, violations)
}

export async function api<T = unknown>(method: string, path: string, options: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/ld+json, application/json' }
  const token = useAuth.getState().token
  if (token) headers.Authorization = `Bearer ${token}`

  let body: BodyInit | undefined
  if (options.form) {
    body = options.form
  } else if (options.json !== undefined) {
    headers['Content-Type'] = options.contentType ?? (method === 'PATCH' ? 'application/merge-patch+json' : 'application/json')
    body = JSON.stringify(options.json)
  }

  const response = await fetch(buildUrl(path, options.query), { method, headers, body, signal: options.signal })

  if (response.status === 401 && token && !path.startsWith('/api/auth/')) {
    useAuth.getState().logout()
    if (!window.location.pathname.startsWith('/login')) window.location.assign('/login?expired=1')
  }
  if (!response.ok) throw await parseError(response)
  if (response.status === 204) return undefined as T
  const text = await response.text()
  return (text ? JSON.parse(text) : undefined) as T
}

/** Normalises an API Platform JSON-LD collection (with or without the hydra: prefix). */
export function asCollection<T>(data: unknown): Collection<T> {
  const d = data as Record<string, unknown>
  const member = (d.member ?? d['hydra:member'] ?? []) as T[]
  const totalItems = (d.totalItems ?? d['hydra:totalItems'] ?? member.length) as number
  return { member, totalItems }
}

export const get = <T,>(path: string, query?: RequestOptions['query'], signal?: AbortSignal) => api<T>('GET', path, { query, signal })
export const post = <T,>(path: string, json?: unknown) => api<T>('POST', path, { json: json ?? {} })
export const patch = <T,>(path: string, json: unknown, contentType?: string) => api<T>('PATCH', path, { json, contentType })
export const del = (path: string) => api<void>('DELETE', path)
export const iri = (resource: string, id: string) => `/api/${resource}/${id}`
