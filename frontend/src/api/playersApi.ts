import { apiRequest } from './http'

export type PlayerResponse = {
  id: string
  tenantId: string
  email: string
  status: string
  createdAt: string
}

export type RegisterPlayerInput = {
  playerId: string
  tenantId: string
  email: string
}

export async function listPlayers(tenantId: string): Promise<PlayerResponse[]> {
  const params = new URLSearchParams({ tenantId })
  return apiRequest<PlayerResponse[]>(`/api/players?${params.toString()}`, { method: 'GET' })
}

export async function registerPlayer(input: RegisterPlayerInput): Promise<PlayerResponse> {
  return apiRequest<PlayerResponse>('/api/players', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export async function getPlayer(id: string): Promise<PlayerResponse> {
  return apiRequest<PlayerResponse>(`/api/players/${encodeURIComponent(id)}`, { method: 'GET' })
}

export async function blockPlayer(id: string): Promise<PlayerResponse> {
  return apiRequest<PlayerResponse>(`/api/players/${encodeURIComponent(id)}/block`, {
    method: 'POST',
  })
}
