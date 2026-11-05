import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

export function Card({
  title,
  action,
  footer,
  children,
  className,
  bodyClassName,
}: {
  title?: ReactNode
  action?: ReactNode
  footer?: ReactNode
  children: ReactNode
  className?: string
  bodyClassName?: string
}) {
  return (
    <section className={cn('bt-panel', className)}>
      {title || action ? (
        <header className="bt-panel__header">
          {typeof title === 'string' ? <h3 className="bt-panel__title">{title}</h3> : title}
          {action}
        </header>
      ) : null}
      <div className={cn('bt-panel__body', bodyClassName)}>{children}</div>
      {footer ? <footer className="bt-panel__footer">{footer}</footer> : null}
    </section>
  )
}
