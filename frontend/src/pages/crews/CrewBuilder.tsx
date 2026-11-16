import { ArrowDown, ArrowUp, Bot, Plus, Save, X } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'
import { CrewNetwork } from '../../components/CrewNetwork'
import { ModelPicker } from '../../components/ModelPicker'
import { Button, Card, Field, Input, LevelBadge, PageHeader, Select, Skeleton, Tabs, Textarea } from '../../components/ui'
import { ApiError } from '../../lib/api'
import { useAgents, useCrew, useSaveCrew } from '../../lib/queries'
import type { Provider } from '../../lib/types'

interface MemberDraft { agentId: string; role: string }

export default function CrewBuilder() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { data: existing, isLoading } = useCrew(id)
  const { data: agents } = useAgents()
  const save = useSaveCrew()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [form, setForm] = useState({
    name: '', description: '', goal: '', strategy: 'supervisor' as 'supervisor' | 'sequential',
    supervisorProvider: 'mock' as Provider, supervisorModel: 'zyntara-mock-1', maxRounds: 6,
  })
  const [members, setMembers] = useState<MemberDraft[]>([])

  useEffect(() => {
    if (!existing) return
    setForm({
      name: existing.name, description: existing.description ?? '', goal: existing.goal, strategy: existing.strategy,
      supervisorProvider: existing.supervisorProvider, supervisorModel: existing.supervisorModel, maxRounds: existing.maxRounds,
    })
    setMembers(existing.members.map((m) => ({ agentId: m.agent.id, role: m.role })))
  }, [existing])

  const onModel = useCallback((p: Provider, m: string) => setForm((f) => ({ ...f, supervisorProvider: p, supervisorModel: m })), [])
  const agentById = (aid: string) => agents?.member.find((a) => a.id === aid)
  const move = (i: number, d: -1 | 1) => setMembers((ms) => {
    const next = [...ms]
    ;[next[i], next[i + d]] = [next[i + d], next[i]]
    return next
  })

  const submit = async () => {
    setErrors({})
    const roles = members.map((m) => m.role.trim().toLowerCase())
    if (new Set(roles).size !== roles.length) {
      toast.error('Each member needs a distinct role.')
      return
    }
    try {
      const crew = await save.mutateAsync({
        id,
        data: {
          ...form,
          description: form.description || null,
          members: members.map((m, i) => ({ agent: `/api/agents/${m.agentId}`, role: m.role.trim(), position: i })),
        },
      })
      toast.success(id ? 'Crew updated' : 'Crew assembled')
      navigate(`/crews/${crew.id}`)
    } catch (err) {
      if (err instanceof ApiError) setErrors(err.fieldErrors())
    }
  }

  if (id && isLoading) return <Skeleton className="h-96" />
  const unused = agents?.member.filter((a) => !members.some((m) => m.agentId === a.id)) ?? []

  return (
    <>
      <PageHeader eyebrow={id ? 'Edit crew' : 'New crew'} title={id ? existing?.name : 'Assemble a crew'}
        subtitle="Pick the agents, give each a role, and set the shared goal."
        actions={<Link to="/crews"><Button variant="ghost">Cancel</Button></Link>} />

      <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div className="space-y-4">
          <Card className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Name" error={errors.name}><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Launch brief crew" /></Field>
              <Field label="Description"><Input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Optional" /></Field>
            </div>
            <Field label="Shared goal" error={errors.goal} hint="The supervisor drives every member towards this.">
              <Textarea rows={3} value={form.goal} onChange={(e) => setForm({ ...form, goal: e.target.value })} placeholder="Produce a reviewed one-page brief with a working code sample." />
            </Field>
          </Card>

          <Card>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="font-display font-bold">Members</h2>
              <span className="text-xs text-sand-500">2 to 8 agents</span>
            </div>
            {errors.members && <p className="mb-2 text-xs text-red-400">{errors.members}</p>}
            <ul className="space-y-2">
              {members.map((m, i) => {
                const a = agentById(m.agentId)
                return (
                  <li key={m.agentId} className="flex flex-wrap items-center gap-2 rounded-xl border border-white/[0.07] bg-white/[0.02] p-2.5">
                    <span className="grid size-7 place-items-center rounded-lg bg-ember-500 text-xs font-black text-ink-950">{i + 1}</span>
                    <Input className="w-36 py-1.5" value={m.role} aria-label="Role" placeholder="Role"
                      onChange={(e) => setMembers(members.map((x, j) => (j === i ? { ...x, role: e.target.value } : x)))} />
                    <div className="min-w-0 flex-1"><div className="truncate text-sm font-semibold">{a?.name ?? 'Unknown agent'}</div>{a && <LevelBadge level={a.level} />}</div>
                    <button aria-label="Move up" disabled={i === 0} onClick={() => move(i, -1)} className="rounded p-1 text-sand-400 hover:text-sand-50 disabled:opacity-30"><ArrowUp className="size-4" /></button>
                    <button aria-label="Move down" disabled={i === members.length - 1} onClick={() => move(i, 1)} className="rounded p-1 text-sand-400 hover:text-sand-50 disabled:opacity-30"><ArrowDown className="size-4" /></button>
                    <button aria-label="Remove" onClick={() => setMembers(members.filter((_, j) => j !== i))} className="rounded p-1 text-sand-400 hover:text-red-300"><X className="size-4" /></button>
                  </li>
                )
              })}
            </ul>
            {unused.length > 0 ? (
              <div className="mt-3 flex gap-2">
                <Select aria-label="Add agent" defaultValue="" onChange={(e) => {
                  const a = agentById(e.target.value)
                  if (a) setMembers([...members, { agentId: a.id, role: a.name.split(' ')[0] }])
                  e.target.value = ''
                }}>
                  <option value="" disabled>+ Add an agent…</option>
                  {unused.map((a) => <option key={a.id} value={a.id}>{a.name} ({a.level})</option>)}
                </Select>
              </div>
            ) : !agents?.member.length && (
              <p className="mt-3 text-sm text-sand-400"><Bot className="mr-1 inline size-4" />You need agents first. <Link to="/agents/new" className="text-ember-400 hover:underline">Create one</Link>.</p>
            )}
          </Card>

          <Card className="space-y-4">
            <h2 className="font-display font-bold">Orchestration</h2>
            <Tabs value={form.strategy} onChange={(v) => setForm({ ...form, strategy: v })}
              items={[{ value: 'supervisor', label: 'Supervisor' }, { value: 'sequential', label: 'Sequential' }]} />
            <p className="text-xs text-sand-400">
              {form.strategy === 'supervisor'
                ? 'An LLM supervisor reads the work so far and decides which member acts next, or finishes.'
                : 'Members act once each in the order above, each one building on the previous output.'}
            </p>
            <ModelPicker provider={form.supervisorProvider} model={form.supervisorModel} onChange={onModel} />
            <Field label={`Max rounds · ${form.maxRounds}`}>
              <input type="range" min={1} max={20} value={form.maxRounds} onChange={(e) => setForm({ ...form, maxRounds: Number(e.target.value) })} className="w-full accent-ember-500" />
            </Field>
          </Card>

          <div className="flex justify-end">
            <Button onClick={submit} loading={save.isPending} disabled={!form.name || !form.goal || members.length < 2} icon={<Save className="size-4" />}>{id ? 'Save crew' : 'Create crew'}</Button>
          </div>
        </div>

        <Card className="h-fit lg:sticky lg:top-8">
          <h3 className="mb-2 text-center font-display text-sm font-bold">Team network</h3>
          {members.length ? (
            <CrewNetwork members={members.map((m) => ({ role: m.role || '?', name: agentById(m.agentId)?.name ?? '' }))} supervisor={form.strategy === 'supervisor'} />
          ) : (
            <div className="grid aspect-square place-items-center text-sm text-sand-500"><span><Plus className="mx-auto mb-2 size-6" />Add agents to see the network</span></div>
          )}
        </Card>
      </div>
    </>
  )
}
