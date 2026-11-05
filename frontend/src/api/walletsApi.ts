import { apiRequest } from './http'

export type WalletResponse = {
  id: string
  playerId: string
  currency: string
  available: number
  reserved: number
  status: string
}

export async function listWallets(playerId: string): Promise<WalletResponse[]> {
  const params = new URLSearchParams({ playerId })
  return apiRequest<WalletResponse[]>(`/api/wallets?${params.toString()}`, { method: 'GET' })
}

export async function getWallet(id: string): Promise<WalletResponse> {
  return apiRequest<WalletResponse>(`/api/wallets/${encodeURIComponent(id)}`, { method: 'GET' })
}
