import { apiRequest } from './http'

export type AnalyticsRange = {
  from: string
  to: string
  tenantId: string
}

export type BetsByDayResponse = {
  from: string
  to: string
  days: Array<{
    day: string
    betsCount: number
    amountMinor: number
  }>
}

export type GgrByDayResponse = {
  from: string
  to: string
  days: Array<{
    day: string
    stakeMinor: number
    payoutMinor: number
    ggrMinor: number
  }>
}

function analyticsQuery(range: AnalyticsRange): string {
  return new URLSearchParams({
    from: range.from,
    to: range.to,
    tenantId: range.tenantId,
  }).toString()
}

export async function getBetsByDay(range: AnalyticsRange): Promise<BetsByDayResponse> {
  return apiRequest<BetsByDayResponse>(`/api/analytics/bets-by-day?${analyticsQuery(range)}`, {
    method: 'GET',
  })
}

export async function getGgrByDay(range: AnalyticsRange): Promise<GgrByDayResponse> {
  return apiRequest<GgrByDayResponse>(`/api/analytics/ggr-by-day?${analyticsQuery(range)}`, {
    method: 'GET',
  })
}
