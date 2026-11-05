const money = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  minimumFractionDigits: 2,
})

const compactMoney = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  notation: 'compact',
  maximumFractionDigits: 1,
})

const number = new Intl.NumberFormat('en-US')

const percent = new Intl.NumberFormat('en-US', {
  style: 'percent',
  minimumFractionDigits: 1,
  maximumFractionDigits: 1,
})

export function formatMoney(value: number): string {
  return money.format(value)
}

export function formatCompactMoney(value: number): string {
  return compactMoney.format(value)
}

export function formatNumber(value: number): string {
  return number.format(value)
}

export function formatPercent(value: number): string {
  return percent.format(value)
}

export function formatDelta(value: number): string {
  const sign = value > 0 ? '+' : ''
  return `${sign}${formatPercent(value)}`
}

export function formatDate(value: string | Date): string {
  const date = typeof value === 'string' ? new Date(value) : value
  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

export function formatDateTime(value: string | Date): string {
  const date = typeof value === 'string' ? new Date(value) : value
  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

/** Convert minor units (cents) to major for display */
export function fromMinor(minor: number): number {
  return minor / 100
}

export function formatMinor(minor: number): string {
  return formatMoney(fromMinor(minor))
}
