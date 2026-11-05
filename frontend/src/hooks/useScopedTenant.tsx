import { useState } from 'react'
import { listTenantsRequest, type TenantResponse } from '@/api/tenantsApi'
import { useAuth } from '@/auth/AuthContext'
import { Select } from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { TENANT_ID } from '@/lib/demoIds'

export function useScopedTenant() {
  const { user } = useAuth()
  const tenantsQuery = useAsyncQuery(async () => {
    try {
      return await listTenantsRequest()
    } catch {
      return [] as TenantResponse[]
    }
  }, [])
  const [override, setOverride] = useState<string | null>(null)
  const tenantId = override ?? user?.tenantId ?? TENANT_ID

  return {
    user,
    tenantId,
    setTenantId: setOverride,
    tenants: tenantsQuery.data ?? [],
  }
}

export function TenantSelect({
  tenantId,
  tenants,
  onChange,
  label,
}: {
  tenantId: string
  tenants: TenantResponse[]
  onChange: (tenantId: string) => void
  label: string
}) {
  const options = tenants.some((item) => item.id === tenantId)
    ? tenants
    : [{ id: tenantId, name: tenantId, status: 'active' }, ...tenants]

  return (
    <Select
      aria-label={label}
      value={tenantId}
      onChange={(event) => onChange(event.target.value)}
      options={options.map((item) => ({ value: item.id, label: item.name }))}
    />
  )
}
