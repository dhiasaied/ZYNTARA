import { useCallback, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { ModelPicker } from '../components/ModelPicker'
import { Button, Card, Field, Input, PageHeader, Toggle } from '../components/ui'
import { ApiError, patch, post } from '../lib/api'
import type { Provider, User } from '../lib/types'
import { useAuth } from '../stores/auth'

export default function Settings() {
  const { user, setUser } = useAuth()
  const [fullName, setFullName] = useState(user?.fullName ?? '')
  const [prefs, setPrefs] = useState(user!.preferences)
  const [saving, setSaving] = useState(false)
  const [pw, setPw] = useState({ currentPassword: '', newPassword: '' })
  const [pwErrors, setPwErrors] = useState<Record<string, string>>({})
  const [pwSaving, setPwSaving] = useState(false)
  const onModel = useCallback((p: Provider, m: string) => setPrefs((x) => ({ ...x, defaultProvider: p, defaultModel: m })), [])

  const saveProfile = async (e: FormEvent) => {
    e.preventDefault()
    setSaving(true)
    try {
      setUser(await patch<User>('/api/me', { fullName, preferences: prefs }, 'application/json'))
      toast.success('Settings saved')
    } finally {
      setSaving(false)
    }
  }

  const changePassword = async (e: FormEvent) => {
    e.preventDefault()
    setPwSaving(true)
    setPwErrors({})
    try {
      await post('/api/me/password', pw)
      setPw({ currentPassword: '', newPassword: '' })
      toast.success('Password changed')
    } catch (err) {
      if (err instanceof ApiError) setPwErrors(err.fieldErrors())
    } finally {
      setPwSaving(false)
    }
  }

  return (
    <>
      <PageHeader eyebrow="Account" title="Settings" />
      <div className="grid gap-6 lg:grid-cols-2">
        <form onSubmit={saveProfile}>
          <Card className="space-y-4">
            <h2 className="font-display font-bold">Profile & defaults</h2>
            <Field label="Email"><Input value={user?.email} disabled /></Field>
            <Field label="Full name"><Input value={fullName} onChange={(e) => setFullName(e.target.value)} required minLength={2} /></Field>
            <div>
              <span className="label">Default model for new agents and the playground</span>
              <ModelPicker provider={prefs.defaultProvider} model={prefs.defaultModel} onChange={onModel} />
            </div>
            <Toggle checked={prefs.toastNotifications} onChange={(v) => setPrefs({ ...prefs, toastNotifications: v })} label="Pop-up notifications" description="Show a toast when a background run finishes or a document is indexed. The inbox always keeps them." />
            <div className="flex justify-end"><Button type="submit" loading={saving}>Save</Button></div>
          </Card>
        </form>

        <form onSubmit={changePassword}>
          <Card className="space-y-4">
            <h2 className="font-display font-bold">Password</h2>
            <Field label="Current password" error={pwErrors.currentPassword}><Input type="password" autoComplete="current-password" value={pw.currentPassword} onChange={(e) => setPw({ ...pw, currentPassword: e.target.value })} required /></Field>
            <Field label="New password" error={pwErrors.newPassword} hint="At least 8 characters."><Input type="password" autoComplete="new-password" minLength={8} value={pw.newPassword} onChange={(e) => setPw({ ...pw, newPassword: e.target.value })} required /></Field>
            <div className="flex justify-end"><Button type="submit" variant="secondary" loading={pwSaving}>Change password</Button></div>
          </Card>
        </form>
      </div>
    </>
  )
}
