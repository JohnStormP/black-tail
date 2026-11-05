import { cn } from '@/lib/cn'
import type { SelectHTMLAttributes } from 'react'

type Option = { value: string; label: string }

type Props = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string
  hint?: string
  error?: string
  options: Option[]
  placeholder?: string
}

export function Select({
  label,
  hint,
  error,
  options,
  placeholder,
  className,
  id,
  ...props
}: Props) {
  const selectId = id ?? props.name
  return (
    <label className="bt-field" htmlFor={selectId}>
      {label ? <span className="bt-label">{label}</span> : null}
      <select
        id={selectId}
        className={cn('bt-select', error && 'bt-select--error', className)}
        {...props}
      >
        {placeholder ? (
          <option value="" disabled>
            {placeholder}
          </option>
        ) : null}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error ? <span className="bt-error-text">{error}</span> : null}
      {!error && hint ? <span className="bt-help">{hint}</span> : null}
    </label>
  )
}
