import { cn } from '@/lib/cn'
import { formatDelta } from '@/lib/format'

export function Stat({
  label,
  value,
  delta,
  hint,
  compact = false,
}: {
  label: string
  value: string
  delta?: number
  hint?: string
  compact?: boolean
}) {
  const deltaTone =
    delta === undefined ? null : delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat'

  return (
    <div className="bt-stat">
      <p className="bt-stat__label">{label}</p>
      <p className={cn('bt-stat__value', 'bt-numeric')} style={compact ? { fontSize: '1.35rem' } : undefined}>
        {value}
      </p>
      {(delta !== undefined || hint) && (
        <div className="bt-stat__meta">
          {delta !== undefined && deltaTone ? (
            <span className={cn('bt-delta', `bt-delta--${deltaTone}`)}>{formatDelta(delta)}</span>
          ) : null}
          {hint ? <span>{hint}</span> : null}
        </div>
      )}
    </div>
  )
}
