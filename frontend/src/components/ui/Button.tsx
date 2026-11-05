import { cn } from '@/lib/cn'
import type { ButtonHTMLAttributes, ReactNode } from 'react'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
type Size = 'sm' | 'md' | 'lg'

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: Variant
  size?: Size
  loading?: boolean
  leftIcon?: ReactNode
  rightIcon?: ReactNode
}

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  leftIcon,
  rightIcon,
  className,
  children,
  disabled,
  ...props
}: Props) {
  return (
    <button
      className={cn(
        'bt-btn',
        `bt-btn--${variant}`,
        size !== 'md' && `bt-btn--${size}`,
        className,
      )}
      disabled={disabled || loading}
      {...props}
    >
      {loading ? <span className="bt-spinner" /> : leftIcon}
      {children}
      {!loading && rightIcon}
    </button>
  )
}
