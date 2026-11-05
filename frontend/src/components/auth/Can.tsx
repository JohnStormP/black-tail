import type { ReactNode } from 'react'
import { useAuth } from '@/auth/AuthContext'
import type { Permission } from '@/auth/permissions'

export function Can({
  permission,
  children,
  fallback = null,
}: {
  permission: Permission
  children: ReactNode
  fallback?: ReactNode
}) {
  const { can } = useAuth()
  return can(permission) ? <>{children}</> : <>{fallback}</>
}
