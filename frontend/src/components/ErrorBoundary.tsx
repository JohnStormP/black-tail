import { Component, type ErrorInfo, type ReactNode } from 'react'
import { Button } from '@/components/ui'

type Props = { children: ReactNode }
type State = { error: Error | null }

export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('UI crash', error, info)
  }

  render() {
    if (this.state.error) {
      return (
        <div className="bt-page" role="alert">
          <div className="bt-empty">
            <h3 className="bt-empty__title">Something broke</h3>
            <p className="bt-empty__desc">{this.state.error.message}</p>
            <Button
              onClick={() => {
                this.setState({ error: null })
                window.location.assign('/')
              }}
            >
              Reload app
            </Button>
          </div>
        </div>
      )
    }
    return this.props.children
  }
}
