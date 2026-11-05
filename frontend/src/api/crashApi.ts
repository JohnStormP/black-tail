import { apiRequest } from './http'

export type CrashRoundResponse = {
  id: string
  status: string
  createdAt: string
  startedAt: string | null
  crashedAt: string | null
  crashPointHundredths?: number
}

export type CrashBetResponse = {
  id: string
  roundId: string
  playerId: string
  walletId: string
  amountMinor: number
  currency: string
  status: string
  cashOutHundredths: number | null
  payoutMinor: number | null
  createdAt: string
}

export type CrashStreamResponse = {
  roundId: string
  status: string
  mercurePublicUrl: string
  topic: string
}

export type PlaceCrashBetInput = {
  betId: string
  roundId: string
  playerId: string
  walletId: string
  reserveEntryId: string
  amountMinor: number
  currency: string
}

export type CashOutCrashBetInput = {
  settleEntryId: string
  payoutEntryId: string
}

export async function openCrashRound(roundId: string): Promise<CrashRoundResponse> {
  return apiRequest<CrashRoundResponse>('/api/crash/rounds', {
    method: 'POST',
    body: JSON.stringify({ roundId }),
  })
}

export async function startCrashRound(roundId: string): Promise<CrashRoundResponse> {
  return apiRequest<CrashRoundResponse>(`/api/crash/rounds/${encodeURIComponent(roundId)}/start`, {
    method: 'POST',
  })
}

export async function placeCrashBet(input: PlaceCrashBetInput): Promise<CrashBetResponse> {
  return apiRequest<CrashBetResponse>('/api/crash/bets', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export async function cashOutCrashBet(
  betId: string,
  input: CashOutCrashBetInput,
): Promise<CrashBetResponse> {
  return apiRequest<CrashBetResponse>(`/api/crash/bets/${encodeURIComponent(betId)}/cash-out`, {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export async function getCrashStream(roundId: string): Promise<CrashStreamResponse> {
  return apiRequest<CrashStreamResponse>(`/api/crash/rounds/${encodeURIComponent(roundId)}/stream`, {
    method: 'GET',
  })
}
