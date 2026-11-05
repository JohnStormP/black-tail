import type { BetsByDayResponse, GgrByDayResponse } from '@/api/analyticsApi'
import type { PaymentResponse } from '@/api/paymentsApi'
import { fromMinor } from '@/lib/format'

export const chartTooltipStyle = {
  background: 'var(--bt-bg-elevated)',
  border: '1px solid var(--bt-border)',
  borderRadius: 8,
}

export type GgrPoint = {
  date: string
  day: string
  revenue: number
  stake: number
  payout: number
}

export type BetsPoint = {
  date: string
  day: string
  bets: number
  stake: number
}

function dayLabel(day: string): string {
  return day.length >= 10 ? day.slice(5, 10) : day
}

export function toGgrPoints(response: GgrByDayResponse): GgrPoint[] {
  return response.days
    .map((row) => ({
      date: row.day,
      day: dayLabel(row.day),
      revenue: fromMinor(row.ggrMinor),
      stake: fromMinor(row.stakeMinor),
      payout: fromMinor(row.payoutMinor),
    }))
    .sort((a, b) => a.date.localeCompare(b.date))
}

export function sumGgr(response: GgrByDayResponse): {
  ggrMinor: number
  stakeMinor: number
  payoutMinor: number
} {
  let ggrMinor = 0
  let stakeMinor = 0
  let payoutMinor = 0
  for (const row of response.days) {
    ggrMinor += row.ggrMinor
    stakeMinor += row.stakeMinor
    payoutMinor += row.payoutMinor
  }
  return { ggrMinor, stakeMinor, payoutMinor }
}

export function toBetsPoints(response: BetsByDayResponse): BetsPoint[] {
  return response.days
    .map((row) => ({
      date: row.day,
      day: dayLabel(row.day),
      bets: row.betsCount,
      stake: fromMinor(row.amountMinor),
    }))
    .sort((a, b) => a.date.localeCompare(b.date))
}

export function sumBets(response: BetsByDayResponse): { bets: number; amountMinor: number } {
  let bets = 0
  let amountMinor = 0
  for (const row of response.days) {
    bets += row.betsCount
    amountMinor += row.amountMinor
  }
  return { bets, amountMinor }
}

export function inDayRange(iso: string, from: string, to: string): boolean {
  const day = iso.slice(0, 10)
  if (from && day < from) return false
  if (to && day > to) return false
  return true
}

export function sumPayments(payments: PaymentResponse[]): {
  depositsMinor: number
  withdrawalsMinor: number
  depositors: number
} {
  let depositsMinor = 0
  let withdrawalsMinor = 0
  const depositors = new Set<string>()
  for (const payment of payments) {
    if (payment.type === 'deposit') {
      depositsMinor += payment.amountMinor
      depositors.add(payment.playerId)
    } else if (payment.type === 'withdrawal') {
      withdrawalsMinor += payment.amountMinor
    }
  }
  return { depositsMinor, withdrawalsMinor, depositors: depositors.size }
}
