import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import { Button, Field, Input } from '../../components/ui'
import { ApiError, post } from '../../lib/api'
import type { User } from '../../lib/types'
import { useAuth } from '../../stores/auth'
import { AuthLayout } from './AuthLayout'

export default function Register() {
  const [form, setForm] = useState({ fullName: '', email: '', password: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const setSession = useAuth((s) => s.setSession)
  const navigate = useNavigate()

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setErrors({})
    try {
      const res = await post<{ token: string; user: User }>('/api/auth/register', form)
      setSession(res.token, res.user)
      navigate('/', { replace: true })
    } catch (err) {
      if (err instanceof ApiError) {
        setErrors(err.fieldErrors())
        if (!err.violations.length) setError(err.message)
      } else setError('Could not create the account.')
    } finally {
      setLoading(false)
    }
  }

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, [k]: e.target.value })

  return (
    <AuthLayout title="Create your studio" subtitle="Build your first AI app in a minute — no API key needed to start.">
      <form onSubmit={submit} className="space-y-4">
        <Field label="Full name" error={errors.fullName}><Input required value={form.fullName} onChange={set('fullName')} autoComplete="name" /></Field>
        <Field label="Email" error={errors.email}><Input type="email" required value={form.email} onChange={set('email')} autoComplete="email" /></Field>
        <Field label="Password" error={errors.password} hint="At least 8 characters, mix letters, digits and symbols.">
          <Input type="password" required minLength={8} value={form.password} onChange={set('password')} autoComplete="new-password" />
        </Field>
        {error && <p role="alert" className="text-sm text-red-400">{error}</p>}
        <Button type="submit" loading={loading} className="w-full" size="lg">Create account</Button>
      </form>
      <p className="mt-6 text-center text-sm text-sand-400">Already have an account? <Link to="/login" className="font-semibold text-ember-400 hover:underline">Sign in</Link></p>
    </AuthLayout>
  )
}
