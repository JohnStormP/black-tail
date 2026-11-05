import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { loginRequest, logoutRequest, meRequest } from '@/api/authApi'
import { ApiError } from '@/api/http'
import { getTenantRequest } from '@/api/tenantsApi'
import { toAuthUser } from './sessionUser'
import type { AuthUser } from './types'
import {
  ROLE_LABELS,
  canAccessPath,
  hasPermission,
  type Permission,
} from './permissions'

export type { AuthUser } from './types'

const STORAGE_KEY = 'blacktail.auth.v2'

type AuthSession = {
  user: AuthUser
}

type AuthContextValue = {
  user: AuthUser | null
  token: string | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  can: (permission: Permission) => boolean
  canPath: (path: string) => boolean
}

function readCachedUser(): AuthUser | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    return (JSON.parse(raw) as AuthSession).user
  } catch {
    return null
  }
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(() => readCachedUser())
  const [loading, setLoading] = useState(true)

  const persist = useCallback((next: AuthUser | null) => {
    setUser(next)
    if (next) localStorage.setItem(STORAGE_KEY, JSON.stringify({ user: next } satisfies AuthSession))
    else localStorage.removeItem(STORAGE_KEY)
  }, [])

  const resolveUser = useCallback(async (me: Awaited<ReturnType<typeof meRequest>>) => {
    let tenant = null
    if (me.tenantId) {
      try {
        tenant = await getTenantRequest(me.tenantId)
      } catch {
        tenant = null
      }
    }
    return toAuthUser(me, tenant)
  }, [])

  useEffect(() => {
    let cancelled = false

    void (async () => {
      try {
        const me = await meRequest()
        const next = await resolveUser(me)
        if (!cancelled) persist(next)
      } catch (error) {
        if (!cancelled && error instanceof ApiError && error.status === 401) {
          persist(null)
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()

    return () => {
      cancelled = true
    }
  }, [persist, resolveUser])

  const login = useCallback(
    async (email: string, password: string) => {
      const me = await loginRequest(email, password)
      persist(await resolveUser(me))
    },
    [persist, resolveUser],
  )

  const logout = useCallback(() => {
    void logoutRequest().catch(() => undefined)
    persist(null)
  }, [persist])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token: user ? 'session' : null,
      loading,
      login,
      logout,
      can: (permission) => (user ? hasPermission(user.role, permission) : false),
      canPath: (path) => (user ? canAccessPath(user.role, path) : false),
    }),
    [user, loading, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}

export { ROLE_LABELS }
