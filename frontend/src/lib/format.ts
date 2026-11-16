export function timeAgo(iso: string | null | undefined): string {
  if (!iso) return '—'
  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000)
  if (seconds < 45) return 'just now'
  if (seconds < 3600) return `${Math.round(seconds / 60)} min ago`
  if (seconds < 86400) return `${Math.round(seconds / 3600)} h ago`
  if (seconds < 604800) return `${Math.round(seconds / 86400)} d ago`
  return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}

export function duration(ms: number | null | undefined): string {
  if (ms === null || ms === undefined) return '—'
  if (ms < 1000) return `${ms} ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)} s`
  return `${Math.floor(ms / 60000)} min ${Math.round((ms % 60000) / 1000)} s`
}

export function bytes(n: number): string {
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / 1024 / 1024).toFixed(1)} MB`
}

export function compact(n: number): string {
  return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(n)
}

export const LEVELS = [
  { key: 'prompt', label: 'Prompt', short: 'PROMPT', blurb: 'Talk to the LLM directly.' },
  { key: 'app', label: 'AI App', short: 'APP', blurb: 'Wrap the LLM with a purpose and a prompt.' },
  { key: 'tools', label: 'Tools', short: 'TOOLS', blurb: 'Let it send emails, search, query data.' },
  { key: 'rag', label: 'RAG', short: 'RAG', blurb: 'Answer only from your documents.' },
  { key: 'agent', label: 'AI Agent', short: 'AGENT', blurb: 'Plans, acts and decides on its own.' },
  { key: 'agentic', label: 'Agentic AI', short: 'AGENTIC', blurb: 'Several agents working as a team.' },
] as const

export const VERTICALS: Record<string, string> = {
  generic: 'General',
  ecommerce: 'E-commerce',
  education: 'Education',
  support: 'Support',
  email: 'Email',
  research: 'Research',
}
