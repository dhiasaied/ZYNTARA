import { lazy, Suspense, type ReactNode } from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router'
import { AppShell } from './components/AppShell'
import { Spinner } from './components/ui'
import { useAuth } from './stores/auth'

const Login = lazy(() => import('./pages/auth/Login'))
const Register = lazy(() => import('./pages/auth/Register'))
const Dashboard = lazy(() => import('./pages/Dashboard'))
const Playground = lazy(() => import('./pages/Playground'))
const Agents = lazy(() => import('./pages/agents/Agents'))
const AgentBuilder = lazy(() => import('./pages/agents/AgentBuilder'))
const AgentChat = lazy(() => import('./pages/agents/AgentChat'))
const Knowledge = lazy(() => import('./pages/knowledge/Knowledge'))
const KnowledgeDetail = lazy(() => import('./pages/knowledge/KnowledgeDetail'))
const Crews = lazy(() => import('./pages/crews/Crews'))
const CrewBuilder = lazy(() => import('./pages/crews/CrewBuilder'))
const CrewDetail = lazy(() => import('./pages/crews/CrewDetail'))
const Runs = lazy(() => import('./pages/runs/Runs'))
const RunDetail = lazy(() => import('./pages/runs/RunDetail'))
const Analytics = lazy(() => import('./pages/Analytics'))
const Notifications = lazy(() => import('./pages/Notifications'))
const Settings = lazy(() => import('./pages/Settings'))
const Admin = lazy(() => import('./pages/admin/Admin'))
const NotFound = lazy(() => import('./pages/NotFound'))

function RequireAuth({ children }: { children: ReactNode }) {
  const token = useAuth((s) => s.token)
  const location = useLocation()
  if (!token) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  return <>{children}</>
}

function RequireAdmin({ children }: { children: ReactNode }) {
  const isAdmin = useAuth((s) => s.user?.isAdmin)
  return isAdmin ? <>{children}</> : <Navigate to="/" replace />
}

function GuestOnly({ children }: { children: ReactNode }) {
  const token = useAuth((s) => s.token)
  return token ? <Navigate to="/" replace /> : <>{children}</>
}

const Fallback = () => <div className="grid min-h-[50vh] place-items-center"><Spinner className="size-7" /></div>

export default function App() {
  return (
    <Suspense fallback={<Fallback />}>
      <Routes>
        <Route path="/login" element={<GuestOnly><Login /></GuestOnly>} />
        <Route path="/register" element={<GuestOnly><Register /></GuestOnly>} />
        <Route element={<RequireAuth><AppShell /></RequireAuth>}>
          <Route index element={<Dashboard />} />
          <Route path="playground" element={<Playground />} />
          <Route path="agents" element={<Agents />} />
          <Route path="agents/new" element={<AgentBuilder />} />
          <Route path="agents/:id/edit" element={<AgentBuilder />} />
          <Route path="agents/:id" element={<AgentChat />} />
          <Route path="agents/:id/chat/:conversationId" element={<AgentChat />} />
          <Route path="knowledge" element={<Knowledge />} />
          <Route path="knowledge/:id" element={<KnowledgeDetail />} />
          <Route path="crews" element={<Crews />} />
          <Route path="crews/new" element={<CrewBuilder />} />
          <Route path="crews/:id/edit" element={<CrewBuilder />} />
          <Route path="crews/:id" element={<CrewDetail />} />
          <Route path="runs" element={<Runs />} />
          <Route path="runs/:id" element={<RunDetail />} />
          <Route path="analytics" element={<Analytics />} />
          <Route path="notifications" element={<Notifications />} />
          <Route path="settings" element={<Settings />} />
          <Route path="admin" element={<RequireAdmin><Admin /></RequireAdmin>} />
          <Route path="*" element={<NotFound />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
