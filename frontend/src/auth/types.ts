import type { RoleId } from './permissions'

export type AuthUser = {
  id: string
  name: string
  email: string
  role: RoleId
  tenantId: string | null
  tenant: string
}
