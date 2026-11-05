import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info'

const icons = {
  neutral: Info,
  success: CheckCircle2,
  warning: AlertTriangle,
  danger: AlertCircle,
  info: Info,
}

export function Alert({
  tone = 'neutral',
  title,
  children,
}: {
  tone?: Tone
  title?: string
  children: ReactNode
}) {
  const Icon = icons[tone]
  return (
    <div className={cn('bt-alert', tone !== 'neutral' && `bt-alert--${tone}`)}>
      <Icon size={16} />
      <div>
        {title ? <strong style={{ display: 'block', marginBottom: 2 }}>{title}</strong> : null}
        <div>{children}</div>
      </div>
    </div>
  )
}
