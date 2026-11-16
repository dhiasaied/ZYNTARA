import { MutationCache, QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import { toast, Toaster } from 'sonner'
import App from './App'
import { ErrorBoundary } from './components/ErrorBoundary'
import { ApiError } from './lib/api'
import './index.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 15_000,
      retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 2,
      refetchOnWindowFocus: false,
    },
  },
  // Every failed mutation surfaces a toast unless the caller handles it inline (meta.silent).
  mutationCache: new MutationCache({
    onError: (error, _vars, _ctx, mutation) => {
      if (mutation.meta?.silent) return
      toast.error(error instanceof Error ? error.message : 'Something went wrong')
    },
  }),
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <App />
          <Toaster theme="dark" position="bottom-right" richColors closeButton
            toastOptions={{ style: { background: '#171411', border: '1px solid rgb(255 255 255 / 0.08)', color: '#f8f3ec' } }} />
        </BrowserRouter>
      </QueryClientProvider>
    </ErrorBoundary>
  </StrictMode>,
)
