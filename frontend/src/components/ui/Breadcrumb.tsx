import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'

export type Crumb = { label: string; to?: string }

export function Breadcrumb({ items }: { items: Crumb[] }) {
  return (
    <nav className="bt-breadcrumb" aria-label="Breadcrumb">
      {items.map((item, index) => {
        const last = index === items.length - 1
        return (
          <span key={`${item.label}-${index}`} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
            {index > 0 ? <ChevronRight size={12} /> : null}
            {item.to && !last ? (
              <Link to={item.to}>{item.label}</Link>
            ) : (
              <span className="bt-breadcrumb__current">{item.label}</span>
            )}
          </span>
        )
      })}
    </nav>
  )
}
