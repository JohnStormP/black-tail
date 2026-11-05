import { apiRequest } from './http'

export type GameResponse = {
  id: string
  code: string
  name: string
  status: string
  createdAt: string
}

export type TenantGameResponse = {
  id: string
  tenantId: string
  gameId: string
  enabled: boolean
  createdAt: string
}

export type GameRoundResponse = {
  id: string
  gameId: string
  playerId: string
  betMinor: number
  stops: number[]
  totalMultiplier: number
  winMinor: number
  isWin: boolean
  createdAt: string
}

export type RegisterGameInput = {
  gameId: string
  code: string
  name: string
}

export type AssignTenantGameInput = {
  tenantGameId: string
  tenantId: string
  gameId: string
}

export type SpinLuckyReelsInput = {
  roundId: string
  gameId: string
  playerId: string
  walletId: string
  betMinor: number
  currency: string
  reserveEntryId: string
  settleEntryId: string
  payoutEntryId: string
}

export async function listGames(): Promise<GameResponse[]> {
  return apiRequest<GameResponse[]>('/api/games', { method: 'GET' })
}

export async function registerGame(input: RegisterGameInput): Promise<GameResponse> {
  return apiRequest<GameResponse>('/api/games', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export async function listTenantGames(tenantId: string): Promise<TenantGameResponse[]> {
  const params = new URLSearchParams({ tenantId })
  return apiRequest<TenantGameResponse[]>(`/api/tenant-games?${params.toString()}`, { method: 'GET' })
}

export async function assignTenantGame(input: AssignTenantGameInput): Promise<TenantGameResponse> {
  return apiRequest<TenantGameResponse>('/api/tenant-games', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export async function enableTenantGame(id: string): Promise<TenantGameResponse> {
  return apiRequest<TenantGameResponse>(`/api/tenant-games/${encodeURIComponent(id)}/enable`, {
    method: 'POST',
  })
}

export async function disableTenantGame(id: string): Promise<TenantGameResponse> {
  return apiRequest<TenantGameResponse>(`/api/tenant-games/${encodeURIComponent(id)}/disable`, {
    method: 'POST',
  })
}

export async function spinLuckyReels(input: SpinLuckyReelsInput): Promise<GameRoundResponse> {
  return apiRequest<GameRoundResponse>('/api/games/lucky-reels/spin', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}
