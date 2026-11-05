import type { ReactNode } from 'react'

export function EmptyState({
  title,
  description,
  action,
  icon,
}: {
  title: string
  description: string
  action?: ReactNode
  icon?: ReactNode
}) {
  return (
    <div className="bt-empty">
      {icon}
      <h3 className="bt-empty__title">{title}</h3>
      <p className="bt-empty__desc">{description}</p>
      {action}
    </div>
  )
}
