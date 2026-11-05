import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

export function ChartContainer({
  children,
  size = 'md',
  className,
}: {
  children: ReactNode
  size?: 'sm' | 'md'
  className?: string
}) {
  return (
    <div className={cn('bt-chart', size === 'sm' && 'bt-chart--sm', className)}>
      {children}
    </div>
  )
}

export const chartTheme = {
  grid: 'var(--bt-chart-grid)',
  axis: 'var(--bt-text-faint)',
  tooltipBg: 'var(--bt-bg-elevated)',
  tooltipBorder: 'var(--bt-border)',
  series: [
    'var(--bt-chart-1)',
    'var(--bt-chart-2)',
    'var(--bt-chart-3)',
    'var(--bt-chart-4)',
    'var(--bt-chart-5)',
  ],
}
