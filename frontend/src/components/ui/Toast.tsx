import { X } from 'lucide-react'
import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { IconButton } from './IconButton'

type ToastTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info'

type ToastItem = {
  id: string
  title: string
  description?: string
  tone?: ToastTone
}

type ToastContextValue = {
  push: (toast: Omit<ToastItem, 'id'>) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([])

  const push = useCallback((toast: Omit<ToastItem, 'id'>) => {
    const id = crypto.randomUUID()
    setItems((current) => [...current, { ...toast, id }])
    window.setTimeout(() => {
      setItems((current) => current.filter((item) => item.id !== id))
    }, 3200)
  }, [])

  const value = useMemo(() => ({ push }), [push])

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="bt-toast-viewport">
        {items.map((item) => (
          <div key={item.id} className="bt-toast" role="status">
            <div style={{ flex: 1 }}>
              <strong style={{ display: 'block', fontSize: 13 }}>{item.title}</strong>
              {item.description ? (
                <span style={{ fontSize: 12, color: 'var(--bt-text-muted)' }}>{item.description}</span>
              ) : null}
            </div>
            <IconButton
              label="Dismiss"
              onClick={() => setItems((current) => current.filter((entry) => entry.id !== item.id))}
            >
              <X size={14} />
            </IconButton>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used within ToastProvider')
  return ctx
}
