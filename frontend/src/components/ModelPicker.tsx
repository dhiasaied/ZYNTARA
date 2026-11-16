import { useEffect } from 'react'
import { useProviders } from '../lib/queries'
import type { Provider } from '../lib/types'
import { Field, Input, Select } from './ui'

/** Provider + model selection. Unavailable providers stay selectable but are flagged. */
export function ModelPicker({ provider, model, onChange, compact }: {
  provider: Provider
  model: string
  onChange: (provider: Provider, model: string) => void
  compact?: boolean
}) {
  const { data: providers = [] } = useProviders()
  const current = providers.find((p) => p.id === provider)

  useEffect(() => {
    // Snap to the provider's default model when the current one does not belong to it.
    if (current && !model) onChange(provider, current.default_model)
  }, [current, model, provider, onChange])

  return (
    <div className={compact ? 'grid grid-cols-2 gap-2' : 'grid gap-4 sm:grid-cols-2'}>
      <Field label="Provider" hint={current && !current.available ? `Not configured on the server — ${current.description ?? ''}` : undefined}>
        <Select value={provider} onChange={(e) => {
          const next = providers.find((p) => p.id === e.target.value)
          onChange(e.target.value as Provider, next?.default_model ?? '')
        }}>
          {providers.map((p) => (
            <option key={p.id} value={p.id}>{p.name}{p.available ? '' : ' (not configured)'}</option>
          ))}
          {providers.length === 0 && <option value={provider}>{provider}</option>}
        </Select>
      </Field>
      <Field label="Model">
        <Input list={`models-${provider}`} value={model} onChange={(e) => onChange(provider, e.target.value)} placeholder="model name" />
        <datalist id={`models-${provider}`}>
          {current?.models.map((m) => <option key={m} value={m} />)}
        </datalist>
      </Field>
    </div>
  )
}
