import clsx from 'clsx'
import { AnimatePresence, motion } from 'framer-motion'
import {
  BookOpenText, Brain, Calculator, Check, ChevronLeft, ChevronRight, Clock, Cpu, Database, Globe, Mail, Save, Wand2,
} from 'lucide-react'
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { ModelPicker } from '../../components/ModelPicker'
import { Button, Card, ChipIcon, Field, Input, LevelBadge, PageHeader, Select, Skeleton, Textarea, Toggle } from '../../components/ui'
import { ApiError } from '../../lib/api'
import { VERTICALS } from '../../lib/format'
import { useAgent, useKnowledgeBases, useSaveAgent, useTemplates, useTools } from '../../lib/queries'
import type { Level, Provider, Template } from '../../lib/types'
import { useAuth } from '../../stores/auth'

const TOOL_ICONS: Record<string, ReactNode> = { mail: <Mail />, globe: <Globe />, database: <Database />, calculator: <Calculator />, clock: <Clock /> }

const STEPS = [
  { key: 'prompt', title: 'Prompt', text: 'Who is it and what is it for?' },
  { key: 'model', title: 'Model', text: 'Which LLM powers it?' },
  { key: 'tools', title: 'Tools', text: 'What can it do in the world?' },
  { key: 'rag', title: 'Knowledge', text: 'Which documents may it use?' },
  { key: 'agent', title: 'Autonomy', text: 'Should it decide on its own?' },
] as const

interface Form {
  name: string
  description: string
  vertical: string
  systemPrompt: string
  provider: Provider
  model: string
  temperature: number
  tools: string[]
  knowledgeBases: string[]
  autonomous: boolean
  maxIterations: number
  memoryEnabled: boolean
}

function levelOf(f: Form): Level {
  if (f.autonomous) return 'agent'
  if (f.knowledgeBases.length) return 'rag'
  if (f.tools.length) return 'tools'
  return 'app'
}

/** The "AI APP" window from the video, with the chip inside and the attached tools wired below. */
function AppPreview({ form, toolIcons }: { form: Form; toolIcons: { key: string; icon: ReactNode }[] }) {
  const level = levelOf(form)
  return (
    <div className="glass overflow-hidden">
      <div className="flex items-center gap-1.5 border-b border-white/[0.06] px-3 py-2">
        <span className="size-2.5 rounded-full bg-[#ff5f57]" /><span className="size-2.5 rounded-full bg-[#febc2e]" /><span className="size-2.5 rounded-full bg-[#28c840]" />
        <span className="ml-auto font-display text-[10px] font-bold tracking-widest text-ember-400">AI APP</span>
      </div>
      <div className="flex flex-col items-center px-4 pt-6 pb-5">
        <ChipIcon size="lg" active>{form.autonomous ? <Brain /> : <Cpu />}</ChipIcon>
        <div className="mt-3 max-w-full truncate font-display font-bold">{form.name || 'Untitled agent'}</div>
        <div className="mt-1"><LevelBadge level={level} /></div>
        <AnimatePresence>
          {(toolIcons.length > 0 || form.knowledgeBases.length > 0) && (
            <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} className="mt-4 w-full">
              <svg className="mx-auto block h-6 w-full" viewBox="0 0 100 24" preserveAspectRatio="none" aria-hidden><path d="M50 0 V12 M10 24 V12 H90 V24" stroke="rgb(255 106 26 / .5)" fill="none" strokeDasharray="2 3" /></svg>
              <div className="flex flex-wrap justify-center gap-2">
                {toolIcons.map((t) => (
                  <motion.span key={t.key} layout initial={{ scale: 0 }} animate={{ scale: 1 }} className="tile grid size-9 place-items-center rounded-xl text-ember-400 [&_svg]:size-4">{t.icon}</motion.span>
                ))}
                {form.knowledgeBases.length > 0 && (
                  <motion.span layout initial={{ scale: 0 }} animate={{ scale: 1 }} className="tile grid size-9 place-items-center rounded-xl text-sky-300 [&_svg]:size-4"><BookOpenText /></motion.span>
                )}
              </div>
            </motion.div>
          )}
        </AnimatePresence>
        {form.autonomous && <p className="mt-4 text-center text-xs text-sand-400">Plans → acts with tools → reviews itself, up to {form.maxIterations} tool rounds.</p>}
      </div>
    </div>
  )
}

export default function AgentBuilder() {
  const { id } = useParams()
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const prefs = useAuth((s) => s.user?.preferences)
  const { data: existing, isLoading } = useAgent(id)
  const { data: tools = [] } = useTools()
  const { data: templates = [] } = useTemplates()
  const { data: kbs } = useKnowledgeBases()
  const save = useSaveAgent()
  const [step, setStep] = useState(0)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [form, setForm] = useState<Form>({
    name: '', description: '', vertical: 'generic', systemPrompt: '',
    provider: prefs?.defaultProvider ?? 'mock', model: prefs?.defaultModel ?? 'zyntara-mock-1', temperature: 0.3,
    tools: [], knowledgeBases: [], autonomous: params.get('level') === 'agent', maxIterations: 6, memoryEnabled: false,
  })

  useEffect(() => {
    if (!existing) return
    setForm({
      name: existing.name, description: existing.description ?? '', vertical: existing.vertical, systemPrompt: existing.systemPrompt,
      provider: existing.provider, model: existing.model, temperature: existing.temperature, tools: existing.tools,
      knowledgeBases: existing.knowledgeBases.map((k) => k.id), autonomous: existing.autonomous, maxIterations: existing.maxIterations,
      memoryEnabled: existing.memoryEnabled,
    })
  }, [existing])

  const set = <K extends keyof Form>(key: K, value: Form[K]) => setForm((f) => ({ ...f, [key]: value }))
  const onModel = useCallback((provider: Provider, model: string) => setForm((f) => ({ ...f, provider, model })), [])
  const toolIcons = useMemo(() => form.tools.map((k) => ({ key: k, icon: TOOL_ICONS[tools.find((t) => t.key === k)?.icon ?? ''] ?? <Wand2 /> })), [form.tools, tools])

  const applyTemplate = (t: Template) => {
    setForm((f) => ({ ...f, name: t.name, description: t.description, vertical: t.vertical, systemPrompt: t.systemPrompt, tools: t.tools, autonomous: t.autonomous }))
    toast.success(`Template "${t.name}" applied`)
  }

  const submit = async () => {
    setErrors({})
    const payload = {
      ...form,
      description: form.description || null,
      knowledgeBases: form.knowledgeBases.map((k) => `/api/knowledge_bases/${k}`),
    }
    try {
      const agent = await save.mutateAsync({ id, data: payload })
      toast.success(id ? 'Agent updated' : 'Agent created — say hello!')
      navigate(`/agents/${agent.id}`)
    } catch (err) {
      if (err instanceof ApiError) {
        const fieldErrors = err.fieldErrors()
        setErrors(fieldErrors)
        if (fieldErrors.name || fieldErrors.systemPrompt) setStep(0)
        else if (fieldErrors.model || fieldErrors.provider) setStep(1)
      }
    }
  }

  if (id && isLoading) return <Skeleton className="h-96" />
  const canNext = step !== 0 || (form.name.trim() && form.systemPrompt.trim())

  return (
    <>
      <PageHeader eyebrow={id ? 'Edit agent' : 'New agent'} title={id ? existing?.name : 'Build an AI app'}
        subtitle="Climb one rung per step. You can stop at any rung — a prompt-only app is already useful."
        actions={<Link to="/agents"><Button variant="ghost">Cancel</Button></Link>} />

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div>
          <ol className="mb-5 grid grid-cols-5 gap-1.5">
            {STEPS.map((s, i) => (
              <li key={s.key}>
                <button type="button" onClick={() => (i === 0 || canNext) && setStep(i)}
                  className={clsx('w-full rounded-xl border p-2.5 text-left transition', i === step ? 'border-ember-500/50 bg-ember-500/10' : 'border-white/[0.06] bg-white/[0.02] hover:border-white/15')}>
                  <div className="flex items-center gap-1.5">
                    <span className={clsx('grid size-5 place-items-center rounded-md text-[10px] font-bold', i < step ? 'bg-ember-500 text-ink-950' : i === step ? 'bg-ember-500/20 text-ember-300' : 'bg-white/5 text-sand-500')}>
                      {i < step ? <Check className="size-3" /> : i + 1}
                    </span>
                    <span className="truncate text-xs font-semibold">{s.title}</span>
                  </div>
                  <p className="mt-1 hidden truncate text-[11px] text-sand-500 md:block">{s.text}</p>
                </button>
              </li>
            ))}
          </ol>

          <AnimatePresence mode="wait">
            <motion.div key={step} initial={{ opacity: 0, x: 16 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -16 }} transition={{ duration: 0.18 }}>
              <Card className="space-y-5">
                {step === 0 && (
                  <>
                    {!id && templates.length > 0 && (
                      <div>
                        <span className="label">Start from a template</span>
                        <div className="grid gap-2 sm:grid-cols-3">
                          {templates.map((t) => (
                            <button key={t.id} type="button" onClick={() => applyTemplate(t)} className="rounded-xl border border-white/[0.07] bg-white/[0.02] p-3 text-left transition hover:border-ember-500/40">
                              <div className="flex items-center justify-between gap-2"><span className="truncate text-sm font-semibold">{t.name}</span><LevelBadge level={t.level} /></div>
                              <p className="mt-1 line-clamp-2 text-xs text-sand-400">{t.description}</p>
                            </button>
                          ))}
                        </div>
                      </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Field label="Name" error={errors.name}><Input value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="e.g. Email assistant" maxLength={120} /></Field>
                      <Field label="Business activity">
                        <Select value={form.vertical} onChange={(e) => set('vertical', e.target.value)}>
                          {Object.entries(VERTICALS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </Select>
                      </Field>
                    </div>
                    <Field label="Description" error={errors.description}><Input value={form.description} onChange={(e) => set('description', e.target.value)} placeholder="What is it for? (optional)" /></Field>
                    <Field label="System prompt" error={errors.systemPrompt} hint="The instructions the LLM always receives. This is what turns a model into an app.">
                      <Textarea rows={7} value={form.systemPrompt} onChange={(e) => set('systemPrompt', e.target.value)} placeholder="You are … Your job is … Always … Never …" />
                    </Field>
                  </>
                )}

                {step === 1 && (
                  <>
                    <ModelPicker provider={form.provider} model={form.model} onChange={onModel} />
                    {errors.model && <p className="text-xs text-red-400">{errors.model}</p>}
                    <div>
                      <label className="label flex justify-between"><span>Temperature</span><span className="font-mono text-ember-300">{form.temperature.toFixed(1)}</span></label>
                      <input type="range" min={0} max={2} step={0.1} value={form.temperature} onChange={(e) => set('temperature', Number(e.target.value))} className="w-full accent-ember-500" />
                      <p className="mt-1 text-xs text-sand-500">Low = precise and repeatable. High = creative.</p>
                    </div>
                  </>
                )}

                {step === 2 && (
                  <div className="grid gap-3 sm:grid-cols-2">
                    {tools.map((t) => {
                      const on = form.tools.includes(t.key)
                      return (
                        <button key={t.key} type="button" aria-pressed={on}
                          onClick={() => set('tools', on ? form.tools.filter((k) => k !== t.key) : [...form.tools, t.key])}
                          className={clsx('flex gap-3 rounded-xl border p-3.5 text-left transition', on ? 'border-ember-500/50 bg-ember-500/10' : 'border-white/[0.07] bg-white/[0.02] hover:border-white/15')}>
                          <ChipIcon size="sm" active={on}>{TOOL_ICONS[t.icon] ?? <Wand2 />}</ChipIcon>
                          <span className="min-w-0 flex-1">
                            <span className="flex items-center justify-between text-sm font-semibold">{t.name}{on && <Check className="size-4 text-ember-400" />}</span>
                            <span className="mt-0.5 block text-xs text-sand-400">{t.description}</span>
                          </span>
                        </button>
                      )
                    })}
                  </div>
                )}

                {step === 3 && (
                  <>
                    <p className="text-sm text-sand-400">With documents attached, the agent retrieves the most relevant passages before answering and is instructed to answer <strong className="text-sand-50">only from them</strong>, citing the source.</p>
                    {kbs?.member.length ? (
                      <div className="grid gap-2 sm:grid-cols-2">
                        {kbs.member.map((kb) => {
                          const on = form.knowledgeBases.includes(kb.id)
                          return (
                            <button key={kb.id} type="button" aria-pressed={on}
                              onClick={() => set('knowledgeBases', on ? form.knowledgeBases.filter((k) => k !== kb.id) : [...form.knowledgeBases, kb.id])}
                              className={clsx('flex items-center gap-3 rounded-xl border p-3 text-left transition', on ? 'border-sky-400/50 bg-sky-400/10' : 'border-white/[0.07] bg-white/[0.02] hover:border-white/15')}>
                              <BookOpenText className={clsx('size-5', on ? 'text-sky-300' : 'text-sand-500')} />
                              <span className="min-w-0 flex-1"><span className="block truncate text-sm font-semibold">{kb.name}</span><span className="text-xs text-sand-500">{kb.indexedCount}/{kb.documentCount} documents indexed</span></span>
                              {on && <Check className="size-4 text-sky-300" />}
                            </button>
                          )
                        })}
                      </div>
                    ) : (
                      <div className="rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-sand-400">
                        No knowledge base yet. <Link to="/knowledge" className="text-ember-400 hover:underline">Create one and upload documents</Link>, then come back.
                      </div>
                    )}
                  </>
                )}

                {step === 4 && (
                  <>
                    <Toggle checked={form.autonomous} onChange={(v) => set('autonomous', v)} label="Autonomous agent"
                      description="It writes a plan, chooses and chains tools by itself, then reviews its own answer before replying." />
                    <Field label={`Max tool rounds · ${form.maxIterations}`} hint={form.autonomous ? 'Hard limit on act → observe loops.' : 'Non-autonomous apps use at most 2 tool rounds.'}>
                      <input type="range" min={1} max={25} value={form.maxIterations} onChange={(e) => set('maxIterations', Number(e.target.value))} className="w-full accent-ember-500" />
                    </Field>
                    <Toggle checked={form.memoryEnabled} onChange={(v) => set('memoryEnabled', v)} label="Long-term memory"
                      description="Remembers durable facts across conversations (save_memory / recall_memory)." />
                  </>
                )}
              </Card>
            </motion.div>
          </AnimatePresence>

          <div className="mt-4 flex items-center justify-between">
            <Button variant="ghost" disabled={step === 0} onClick={() => setStep(step - 1)} icon={<ChevronLeft className="size-4" />}>Back</Button>
            <div className="flex gap-2">
              {step < STEPS.length - 1 && <Button variant="secondary" disabled={!canNext} onClick={() => setStep(step + 1)}>Next <ChevronRight className="size-4" /></Button>}
              <Button onClick={submit} loading={save.isPending} disabled={!form.name.trim() || !form.systemPrompt.trim()} icon={<Save className="size-4" />}>
                {id ? 'Save changes' : 'Create agent'}
              </Button>
            </div>
          </div>
        </div>

        <div className="space-y-4 lg:sticky lg:top-8 lg:h-fit">
          <AppPreview form={form} toolIcons={toolIcons} />
          <Card className="text-xs text-sand-400">
            <div className="mb-2 font-semibold text-sand-200">Current rung</div>
            {{ app: 'AI App — the LLM with a purpose. Add tools to let it act.', tools: 'Tools — it can act in the world. Attach documents for grounded answers.', rag: 'RAG — answers only from your documents. Make it autonomous to let it decide.', agent: 'AI Agent — plans, acts and reviews on its own. Put several in a crew for Agentic AI.', prompt: '', agentic: '' }[levelOf(form)]}
          </Card>
        </div>
      </div>
    </>
  )
}
