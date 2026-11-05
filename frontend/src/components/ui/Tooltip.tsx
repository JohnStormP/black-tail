import { useState, type ReactNode } from 'react'

export function Tooltip({ content, children }: { content: string; children: ReactNode }) {
  const [open, setOpen] = useState(false)
  return (
    <span
      style={{ position: 'relative', display: 'inline-flex' }}
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
      onFocus={() => setOpen(true)}
      onBlur={() => setOpen(false)}
    >
      {children}
      {open ? <span className="bt-tooltip" role="tooltip">{content}</span> : null}
    </span>
  )
}
