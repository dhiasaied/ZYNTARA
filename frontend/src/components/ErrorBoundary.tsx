import { Component, type ErrorInfo, type ReactNode } from 'react'

interface State { error: Error | null }

export class ErrorBoundary extends Component<{ children: ReactNode }, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('UI crashed', error, info.componentStack)
  }

  render() {
    if (!this.state.error) return this.props.children
    return (
      <div className="grid min-h-screen place-items-center p-6">
        <div className="glass max-w-md p-8 text-center">
          <h1 className="font-display text-xl font-bold">Something broke on our side</h1>
          <p className="mt-2 text-sm text-sand-400">{this.state.error.message}</p>
          <button className="mt-5 rounded-xl bg-ember-500 px-4 py-2 text-sm font-semibold text-ink-950" onClick={() => window.location.assign('/')}>
            Back to dashboard
          </button>
        </div>
      </div>
    )
  }
}
