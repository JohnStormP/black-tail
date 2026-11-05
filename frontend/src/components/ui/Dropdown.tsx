import { useEffect, useRef, useState, type ReactNode } from 'react'
import { cn } from '@/lib/cn'

export type DropdownItem = {
  id: string
  label: string
  danger?: boolean
  onSelect?: () => void
  separator?: boolean
}

export function Dropdown({
  trigger,
  items,
  align = 'right',
}: {
  trigger: ReactNode
  items: DropdownItem[]
  align?: 'left' | 'right'
}) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const onClick = (event: MouseEvent) => {
      if (!ref.current?.contains(event.target as Node)) setOpen(false)
    }
    window.addEventListener('mousedown', onClick)
    return () => window.removeEventListener('mousedown', onClick)
  }, [open])

  return (
    <div ref={ref} style={{ position: 'relative', display: 'inline-flex' }}>
      <div onClick={() => setOpen((value) => !value)}>{trigger}</div>
      {open ? (
        <div className="bt-dropdown" style={align === 'left' ? { right: 'auto', left: 0 } : undefined}>
          {items.map((item) =>
            item.separator ? (
              <div key={item.id} className="bt-dropdown__sep" />
            ) : (
              <button
                key={item.id}
                type="button"
                className={cn('bt-dropdown__item', item.danger && 'bt-dropdown__item--danger')}
                onClick={() => {
                  item.onSelect?.()
                  setOpen(false)
                }}
              >
                {item.label}
              </button>
            ),
          )}
        </div>
      ) : null}
    </div>
  )
}
