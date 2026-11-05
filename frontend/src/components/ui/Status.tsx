import { cn } from '@/lib/cn'

type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

const labels: Record<string, { tone: Tone; label: string }> = {
  completed: { tone: 'success', label: 'Completed' },
  active: { tone: 'success', label: 'Active' },
  live: { tone: 'success', label: 'Live' },
  pending: { tone: 'warning', label: 'Pending' },
  processing: { tone: 'info', label: 'Processing' },
  invited: { tone: 'info', label: 'Invited' },
  scheduled: { tone: 'info', label: 'Scheduled' },
  paused: { tone: 'warning', label: 'Paused' },
  suspended: { tone: 'warning', label: 'Suspended' },
  failed: { tone: 'danger', label: 'Failed' },
  blocked: { tone: 'danger', label: 'Blocked' },
  expired: { tone: 'danger', label: 'Expired' },
  ended: { tone: 'neutral', label: 'Ended' },
  disabled: { tone: 'neutral', label: 'Disabled' },
  self_excluded: { tone: 'danger', label: 'Self-excluded' },
}

export function Status({ value }: { value: string }) {
  const mapped = labels[value] ?? {
    tone: 'neutral' as Tone,
    label: value.replaceAll('_', ' '),
  }

  return (
    <span className={cn('bt-status', `bt-status--${mapped.tone}`)}>
      <span className="bt-status__dot" />
      {mapped.label}
    </span>
  )
}
