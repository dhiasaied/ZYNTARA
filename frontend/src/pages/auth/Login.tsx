import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router'
import { Button, Field, Input } from '../../components/ui'
import { ApiError, post } from '../../lib/api'
import type { User } from '../../lib/types'
import { useAuth } from '../../stores/auth'
import { AuthLayout } from './AuthLayout'

export default function Login() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const setSession = useAuth((s) => s.setSession)
  const navigate = useNavigate()
  const location = useLocation()
  const [params] = useSearchParams()

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      const res = await post<{ token: string; user: User }>('/api/auth/login', { email, password })
      setSession(res.token, res.user)
      navigate((location.state as { from?: string } | null)?.from ?? '/', { replace: true })
    } catch (err) {
      setError(err instanceof ApiError && err.status === 401 ? (err.message === 'Invalid credentials.' ? 'Wrong email or password.' : err.message) : 'Could not sign in. Try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthLayout title="Welcome back" subtitle="Sign in to keep climbing the ladder.">
      {params.get('expired') && <div className="mb-4 rounded-xl border border-warn-500/30 bg-warn-500/10 p-3 text-sm text-amber-200">Your session expired. Please sign in again.</div>}
      <form onSubmit={submit} className="space-y-4">
        <Field label="Email"><Input type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@company.com" /></Field>
        <Field label="Password"><Input type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" /></Field>
        {error && <p role="alert" className="text-sm text-red-400">{error}</p>}
        <Button type="submit" loading={loading} className="w-full" size="lg">Sign in</Button>
      </form>
      <p className="mt-6 text-center text-sm text-sand-400">New here? <Link to="/register" className="font-semibold text-ember-400 hover:underline">Create an account</Link></p>
    </AuthLayout>
  )
}
