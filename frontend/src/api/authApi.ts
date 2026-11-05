import { apiRequest } from './http'

export type MeResponse = {
  id: string
  email: string
  roles: string[]
  tenantId: string | null
}

export async function loginRequest(email: string, password: string): Promise<MeResponse> {
  return apiRequest<MeResponse>('/api/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })
}

export async function meRequest(): Promise<MeResponse> {
  return apiRequest<MeResponse>('/api/me', { method: 'GET' })
}

export async function logoutRequest(): Promise<void> {
  await apiRequest<unknown>('/api/logout', { method: 'POST' })
}
