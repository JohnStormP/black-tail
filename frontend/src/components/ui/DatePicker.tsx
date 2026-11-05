import { Input } from './Input'
import type { InputHTMLAttributes } from 'react'

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label?: string
  hint?: string
  error?: string
}

export function DatePicker({ label = 'Date', ...props }: Props) {
  return <Input type="date" label={label} {...props} />
}
