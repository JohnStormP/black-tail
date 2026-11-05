import { cn } from '@/lib/cn'
import type { ReactNode } from 'react'

type Tone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info'

export function Badge({
  tone = 'neutral',
  children,
  className,
}: {
  tone?: Tone
  children: ReactNode
  className?: string
}) {
  return (
    <span className={cn('bt-badge', tone !== 'neutral' && `bt-badge--${tone}`, className)}>
      {children}
    </span>
  )
}
