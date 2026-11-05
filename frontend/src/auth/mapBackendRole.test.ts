import { describe, expect, it } from 'vitest'
import { mapBackendRole } from './mapBackendRole'

describe('mapBackendRole', () => {
  it('prefers super admin', () => {
    expect(mapBackendRole(['ROLE_TENANT_ADMIN', 'ROLE_SUPER_ADMIN'])).toBe('platform_admin')
  })

  it('maps tenant admin and analyst', () => {
    expect(mapBackendRole(['ROLE_TENANT_ADMIN'])).toBe('tenant_admin')
    expect(mapBackendRole(['ROLE_ANALYST'])).toBe('risk_analyst')
  })

  it('falls back to support', () => {
    expect(mapBackendRole(['ROLE_SUPPORT'])).toBe('support')
    expect(mapBackendRole([])).toBe('support')
  })
})
