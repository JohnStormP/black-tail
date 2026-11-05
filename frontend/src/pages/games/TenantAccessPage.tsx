import { useState } from 'react'
import { disableTenantGame, enableTenantGame, listGames, listTenantGames } from '@/api/gamesApi'
import { ApiError } from '@/api/http'
import { getTenantRequest } from '@/api/tenantsApi'
import { useAuth } from '@/auth/AuthContext'
import { Can } from '@/components/auth/Can'
import { AsyncState, Breadcrumb, Button, Card, Status, Table, type Column, useToast } from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { useI18n } from '@/i18n/I18nContext'
import { downloadCsv } from '@/lib/csv'
import { TENANT_ID } from '@/lib/demoIds'
import { formatDateTime } from '@/lib/format'

type AccessRow = {
  id: string
  tenant: string
  game: string
  enabled: boolean
  createdAt: string
}

function apiErrorMessage(err: unknown): string {
  if (err instanceof ApiError) return err.message
  if (err instanceof Error && err.message) return err.message
  return 'Request failed'
}

export function TenantAccessPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { user } = useAuth()
  const tenantId = user?.tenantId ?? TENANT_ID
  const [pendingId, setPendingId] = useState<string | null>(null)
  const query = useAsyncQuery(async (): Promise<AccessRow[]> => {
    const [games, links] = await Promise.all([listGames(), listTenantGames(tenantId)])
    let tenantName = tenantId
    try {
      const tenant = await getTenantRequest(tenantId)
      tenantName = tenant.name
    } catch {
      tenantName = tenantId
    }
    const names = new Map(games.map((game) => [game.id, game.name]))
    return links.map((link) => ({
      id: link.id,
      tenant: tenantName,
      game: names.get(link.gameId) ?? link.gameId,
      enabled: link.enabled,
      createdAt: link.createdAt,
    }))
  }, [tenantId])

  const rows = query.data ?? []

  const toggle = async (row: AccessRow) => {
    setPendingId(row.id)
    try {
      if (row.enabled) await disableTenantGame(row.id)
      else await enableTenantGame(row.id)
      toast.push({
        title: row.enabled ? 'Game disabled for tenant' : 'Game enabled for tenant',
        description: `${row.tenant} · ${row.game}`,
        tone: row.enabled ? 'warning' : 'success',
      })
      await query.reload()
    } catch (err) {
      toast.push({
        title: row.enabled ? 'Disable failed' : 'Enable failed',
        description: apiErrorMessage(err),
        tone: 'danger',
      })
    } finally {
      setPendingId(null)
    }
  }

  const columns: Column<AccessRow>[] = [
    { id: 'tenant', header: t('col.tenant'), cell: (row) => row.tenant },
    { id: 'game', header: t('common.games'), cell: (row) => row.game },
    {
      id: 'enabled',
      header: t('common.status'),
      cell: (row) => <Status value={row.enabled ? 'active' : 'disabled'} />,
    },
    { id: 'createdAt', header: t('col.when'), cell: (row) => formatDateTime(row.createdAt) },
    {
      id: 'actions',
      header: '',
      cell: (row) => (
        <Can permission="games.access.write">
          <Button
            size="sm"
            variant={row.enabled ? 'secondary' : 'primary'}
            loading={pendingId === row.id}
            disabled={pendingId !== null && pendingId !== row.id}
            onClick={() => void toggle(row)}
          >
            {row.enabled ? 'Disable' : 'Enable'}
          </Button>
        </Can>
      ),
    },
  ]

  const exportCsv = () => {
    downloadCsv(
      'tenant-game-access.csv',
      rows.map((row) => ({
        tenant: row.tenant,
        game: row.game,
        enabled: row.enabled,
        createdAt: row.createdAt,
      })),
    )
    toast.push({ title: t('common.export'), tone: 'success' })
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.games'), to: '/games' }, { label: t('nav.tenantAccess') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('games.accessTitle')}</h1>
          <p className="bt-page-subtitle">{t('games.accessSub')}</p>
        </div>
        <div className="bt-page-actions">
          <Button variant="secondary" onClick={exportCsv}>
            {t('common.export')}
          </Button>
        </div>
      </div>
      <Card>
        <AsyncState status={query.status} onRetry={query.reload}>
          <Table columns={columns} rows={rows} />
        </AsyncState>
      </Card>
    </div>
  )
}
