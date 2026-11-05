import { cn } from '@/lib/cn'
import type { ButtonHTMLAttributes, ReactNode } from 'react'

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  label: string
  active?: boolean
  children: ReactNode
}

export function IconButton({ label, active, className, children, ...props }: Props) {
  return (
    <button
      type="button"
      aria-label={label}
      className={cn('bt-icon-btn', active && 'bt-icon-btn--active', className)}
      {...props}
    >
      {children}
    </button>
  )
}
