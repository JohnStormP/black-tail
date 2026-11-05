import { cn } from '@/lib/cn'
import type { InputHTMLAttributes } from 'react'

type Props = InputHTMLAttributes<HTMLInputElement> & {
  label?: string
  hint?: string
  error?: string
}

export function Input({ label, hint, error, className, id, ...props }: Props) {
  const inputId = id ?? props.name
  return (
    <label className="bt-field" htmlFor={inputId}>
      {label ? <span className="bt-label">{label}</span> : null}
      <input
        id={inputId}
        className={cn('bt-input', error && 'bt-input--error', className)}
        {...props}
      />
      {error ? <span className="bt-error-text">{error}</span> : null}
      {!error && hint ? <span className="bt-help">{hint}</span> : null}
    </label>
  )
}
