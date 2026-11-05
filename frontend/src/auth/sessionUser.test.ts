import { describe, expect, it } from 'vitest'
import { toAuthUser } from './sessionUser'

describe('toAuthUser', () => {
  it('maps me + tenant name and prefers ROLE_SUPER_ADMIN', () => {
    const user = toAuthUser(
      {
        id: 'u1',
        email: 'admin@example.com',
        roles: ['ROLE_TENANT_ADMIN', 'ROLE_SUPER_ADMIN'],
        tenantId: 't1',
      },
      { id: 't1', name: 'PulseCasino', status: 'active' },
    )

    expect(user).toEqual({
      id: 'u1',
      name: 'admin',
      email: 'admin@example.com',
      role: 'platform_admin',
      tenantId: 't1',
      tenant: 'PulseCasino',
    })
  })

  it('falls back to tenantId when tenant payload is missing', () => {
    const user = toAuthUser(
      {
        id: 'u2',
        email: 'ops@example.com',
        roles: ['ROLE_SUPPORT'],
        tenantId: 't2',
      },
      null,
    )

    expect(user.role).toBe('support')
    expect(user.tenant).toBe('t2')
    expect(user.tenantId).toBe('t2')
  })

  it('uses Platform when there is no tenant', () => {
    const user = toAuthUser(
      {
        id: 'u3',
        email: 'root@example.com',
        roles: ['ROLE_SUPER_ADMIN'],
        tenantId: null,
      },
      null,
    )

    expect(user.tenant).toBe('Platform')
    expect(user.tenantId).toBeNull()
  })
})
