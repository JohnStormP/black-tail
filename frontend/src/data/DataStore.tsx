import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import {
  disableTenantGame,
  enableTenantGame,
  listGames,
  listTenantGames,
  registerGame as registerGameRequest,
  type GameResponse,
  type TenantGameResponse,
} from '@/api/gamesApi'
import { listPayments, type PaymentResponse } from '@/api/paymentsApi'
import { blockPlayer, listPlayers, registerPlayer, type PlayerResponse } from '@/api/playersApi'
import { createTenantRequest, listTenantsRequest, type TenantResponse } from '@/api/tenantsApi'
import { useAuth } from '@/auth/AuthContext'
import { applyAccent } from '@/lib/accent'
import { newUlid } from '@/lib/ulid'

export type Player = {
  id: string
  email: string
  tenantId: string
  status: string
  createdAt: string
}

export type Transaction = {
  id: string
  player: string
  type: 'deposit' | 'withdrawal'
  amount: number
  status: string
  createdAt: string
  method: string
}

export type Game = {
  id: string
  code: string
  name: string
  status: string
  createdAt: string
}

export type TenantGame = {
  id: string
  tenantId: string
  gameId: string
  enabled: boolean
  createdAt: string
}

export type Tenant = {
  id: string
  name: string
  status: string
}

export type NotificationItem = {
  id: string
  title: string
  body: string
  time: string
  unread: boolean
}

export type Settings = {
  workspaceName: string
  currency: string
  timezone: string
  supportEmail: string
  accent: string
  sessionLength: string
  twoFa: string
}

type DataContextValue = {
  players: Player[]
  transactions: Transaction[]
  tenantGames: TenantGame[]
  games: Game[]
  tenants: Tenant[]
  settings: Settings
  notifications: NotificationItem[]
  unreadCount: number
  loading: boolean
  error: string | null
  scopeTenantId: string | null
  reload: () => Promise<void>
  addPlayer: (input: { email: string; tenantId: string }) => Promise<void>
  updatePlayerStatus: (id: string, status: string) => Promise<boolean>
  addTenant: (input: { name: string }) => Promise<void>
  toggleTenantGame: (id: string) => Promise<void>
  registerGame: (input: { code: string; name: string }) => Promise<void>
  saveSettings: (patch: Partial<Settings>) => void
  markNotificationRead: (id: string) => void
  markAllNotificationsRead: () => void
  clearReadNotifications: () => void
}

const DataContext = createContext<DataContextValue | null>(null)
const SETTINGS_KEY = 'blacktail.settings'
const NOTIF_KEY = 'blacktail.notifications'

const defaultSettings: Settings = {
  workspaceName: 'BlackTail',
  currency: 'USD',
  timezone: 'Europe/Kyiv',
  supportEmail: 'support@blacktail.io',
  accent: '#2DD4BF',
  sessionLength: '8h',
  twoFa: 'required',
}

function mapPlayer(player: PlayerResponse): Player {
  return {
    id: player.id,
    email: player.email,
    tenantId: player.tenantId,
    status: player.status,
    createdAt: player.createdAt,
  }
}

function mapTenant(tenant: TenantResponse): Tenant {
  return {
    id: tenant.id,
    name: tenant.name,
    status: tenant.status,
  }
}

function mapGame(game: GameResponse): Game {
  return {
    id: game.id,
    code: game.code,
    name: game.name,
    status: game.status,
    createdAt: game.createdAt,
  }
}

function mapTenantGame(row: TenantGameResponse): TenantGame {
  return {
    id: row.id,
    tenantId: row.tenantId,
    gameId: row.gameId,
    enabled: row.enabled,
    createdAt: row.createdAt,
  }
}

function mapPayment(payment: PaymentResponse): Transaction | null {
  if (payment.type !== 'deposit' && payment.type !== 'withdrawal') return null
  return {
    id: payment.id,
    player: payment.playerId,
    type: payment.type,
    amount: payment.amountMinor / 100,
    status: payment.status,
    createdAt: payment.createdAt,
    method: payment.provider,
  }
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : 'Request failed'
}

function readSettings(): Settings {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    if (!raw) return defaultSettings
    return { ...defaultSettings, ...(JSON.parse(raw) as Partial<Settings>) }
  } catch {
    return defaultSettings
  }
}

function readNotifications(): NotificationItem[] {
  try {
    const raw = localStorage.getItem(NOTIF_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw) as NotificationItem[]
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

export function DataProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const userId = user?.id ?? null
  const userTenantId = user?.tenantId ?? null
  const [players, setPlayers] = useState<Player[]>([])
  const [transactions, setTransactions] = useState<Transaction[]>([])
  const [tenantGames, setTenantGames] = useState<TenantGame[]>([])
  const [games, setGames] = useState<Game[]>([])
  const [tenants, setTenants] = useState<Tenant[]>([])
  const [settings, setSettings] = useState<Settings>(readSettings)
  const [notifications, setNotifications] = useState<NotificationItem[]>(readNotifications)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [scopeTenantId, setScopeTenantId] = useState<string | null>(null)
  const generationRef = useRef(0)
  const scopeOverrideRef = useRef<string | null>(null)
  const tenantGamesRef = useRef(tenantGames)
  tenantGamesRef.current = tenantGames

  const reload = useCallback(async () => {
    const generation = ++generationRef.current
    if (!userId) {
      scopeOverrideRef.current = null
      setScopeTenantId(null)
      setPlayers([])
      setTransactions([])
      setTenantGames([])
      setGames([])
      setTenants([])
      setError(null)
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)
    try {
      const tenantList = (await listTenantsRequest()).map(mapTenant)
      if (generation !== generationRef.current) return
      setTenants(tenantList)
      const scopeId = userTenantId ?? scopeOverrideRef.current ?? tenantList[0]?.id ?? null
      setScopeTenantId(scopeId)
      if (!scopeId) {
        setPlayers([])
        setTransactions([])
        setTenantGames([])
        setGames([])
        return
      }
      const [playerList, gameList, tenantGameList, paymentList] = await Promise.all([
        listPlayers(scopeId),
        listGames(),
        listTenantGames(scopeId),
        listPayments(scopeId),
      ])
      if (generation !== generationRef.current) return
      setPlayers(playerList.map(mapPlayer))
      setGames(gameList.map(mapGame))
      setTenantGames(tenantGameList.map(mapTenantGame))
      setTransactions(paymentList.flatMap((payment) => {
        const mapped = mapPayment(payment)
        return mapped ? [mapped] : []
      }))
    } catch (loadError) {
      if (generation !== generationRef.current) return
      setError(errorMessage(loadError))
    } finally {
      if (generation === generationRef.current) setLoading(false)
    }
  }, [userId, userTenantId])

  useEffect(() => {
    void reload()
  }, [reload])

  useEffect(() => {
    applyAccent(settings.accent)
  }, [settings.accent])

  useEffect(() => {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
  }, [settings])

  useEffect(() => {
    localStorage.setItem(NOTIF_KEY, JSON.stringify(notifications))
  }, [notifications])

  const addPlayer = useCallback(async (input: { email: string; tenantId: string }) => {
    await registerPlayer({
      playerId: newUlid(),
      tenantId: input.tenantId,
      email: input.email,
    })
    if (!userTenantId) scopeOverrideRef.current = input.tenantId
    await reload()
  }, [reload, userTenantId])

  const updatePlayerStatus = useCallback(async (id: string, status: string) => {
    if (status !== 'blocked') return false
    await blockPlayer(id)
    await reload()
    return true
  }, [reload])

  const addTenant = useCallback(async (input: { name: string }) => {
    await createTenantRequest({
      tenantId: newUlid(),
      name: input.name,
    })
    await reload()
  }, [reload])

  const toggleTenantGame = useCallback(async (id: string) => {
    const row = tenantGamesRef.current.find((item) => item.id === id)
    if (!row) return
    if (row.enabled) await disableTenantGame(id)
    else await enableTenantGame(id)
    await reload()
  }, [reload])

  const registerGame = useCallback(async (input: { code: string; name: string }) => {
    await registerGameRequest({
      gameId: newUlid(),
      code: input.code,
      name: input.name,
    })
    await reload()
  }, [reload])

  const saveSettings = useCallback((patch: Partial<Settings>) => {
    setSettings((current) => {
      const next = { ...current, ...patch }
      if (patch.accent) applyAccent(patch.accent)
      return next
    })
  }, [])

  const markNotificationRead = useCallback((id: string) => {
    setNotifications((current) =>
      current.map((item) => (item.id === id ? { ...item, unread: false } : item)),
    )
  }, [])

  const markAllNotificationsRead = useCallback(() => {
    setNotifications((current) => current.map((item) => ({ ...item, unread: false })))
  }, [])

  const clearReadNotifications = useCallback(() => {
    setNotifications((current) => current.filter((item) => item.unread))
  }, [])

  const unreadCount = useMemo(
    () => notifications.filter((item) => item.unread).length,
    [notifications],
  )

  const value = useMemo(
    () => ({
      players,
      transactions,
      tenantGames,
      games,
      tenants,
      settings,
      notifications,
      unreadCount,
      loading,
      error,
      scopeTenantId,
      reload,
      addPlayer,
      updatePlayerStatus,
      addTenant,
      toggleTenantGame,
      registerGame,
      saveSettings,
      markNotificationRead,
      markAllNotificationsRead,
      clearReadNotifications,
    }),
    [
      players,
      transactions,
      tenantGames,
      games,
      tenants,
      settings,
      notifications,
      unreadCount,
      loading,
      error,
      scopeTenantId,
      reload,
      addPlayer,
      updatePlayerStatus,
      addTenant,
      toggleTenantGame,
      registerGame,
      saveSettings,
      markNotificationRead,
      markAllNotificationsRead,
      clearReadNotifications,
    ],
  )

  return <DataContext.Provider value={value}>{children}</DataContext.Provider>
}

export function useData(): DataContextValue {
  const ctx = useContext(DataContext)
  if (!ctx) throw new Error('useData must be used within DataProvider')
  return ctx
}
