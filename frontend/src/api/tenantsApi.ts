import { apiRequest } from './http'

export type TenantResponse = {
  id: string
  name: string
  status: string
}

export type CreateTenantInput = {
  tenantId: string
  name: string
}

export async function listTenantsRequest(): Promise<TenantResponse[]> {
  return apiRequest<TenantResponse[]>('/api/tenants', { method: 'GET' })
}

export async function createTenantRequest(input: CreateTenantInput): Promise<TenantResponse> {
  return apiRequest<TenantResponse>('/api/tenants', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export async function getTenantRequest(tenantId: string): Promise<TenantResponse> {
  return apiRequest<TenantResponse>(`/api/tenants/${encodeURIComponent(tenantId)}`, {
    method: 'GET',
  })
}
