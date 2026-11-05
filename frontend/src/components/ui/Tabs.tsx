import { cn } from '@/lib/cn'

export type TabItem = { id: string; label: string }

export function Tabs({
  items,
  value,
  onChange,
}: {
  items: TabItem[]
  value: string
  onChange: (id: string) => void
}) {
  return (
    <div className="bt-tabs" role="tablist">
      {items.map((item) => (
        <button
          key={item.id}
          type="button"
          role="tab"
          aria-selected={item.id === value}
          className={cn('bt-tab', item.id === value && 'bt-tab--active')}
          onClick={() => onChange(item.id)}
        >
          {item.label}
        </button>
      ))}
    </div>
  )
}
