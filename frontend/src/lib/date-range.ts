export type DatedPoint = { date: string }

export function filterByDateRange<T extends DatedPoint>(rows: T[], from: string, to: string): T[] {
  if (!from && !to) return rows
  return rows.filter((row) => {
    if (from && row.date < from) return false
    if (to && row.date > to) return false
    return true
  })
}

export function lastNDates(anchorIso: string, days: number): { from: string; to: string } {
  const to = new Date(`${anchorIso}T12:00:00Z`)
  const from = new Date(to)
  from.setUTCDate(from.getUTCDate() - (days - 1))
  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
  }
}

export function recentDates(days: number, now: Date = new Date()): { from: string; to: string } {
  return lastNDates(now.toISOString().slice(0, 10), days)
}

export function sumSeries(
  rows: Array<{ revenue?: number; deposits?: number; withdrawals?: number; active?: number; new?: number }>,
): { revenue: number; deposits: number; withdrawals: number; active: number; newPlayers: number } {
  let revenue = 0
  let deposits = 0
  let withdrawals = 0
  let active = 0
  let newPlayers = 0
  for (const row of rows) {
    revenue += row.revenue ?? 0
    deposits += row.deposits ?? 0
    withdrawals += row.withdrawals ?? 0
    active += row.active ?? 0
    newPlayers += row.new ?? 0
  }
  return { revenue, deposits, withdrawals, active, newPlayers }
}
