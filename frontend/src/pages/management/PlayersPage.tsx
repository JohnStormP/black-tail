import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { listPlayers, registerPlayer, type PlayerResponse } from '@/api/playersApi'
import { Can } from '@/components/auth/Can'
import {
  AsyncState,
  Breadcrumb,
  Button,
  Card,
  EmptyState,
  Input,
  Modal,
  Pagination,
  Search,
  Select,
  Status,
  Table,
  type Column,
  useToast,
} from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { TenantSelect, useScopedTenant } from '@/hooks/useScopedTenant'
import { useI18n } from '@/i18n/I18nContext'
import { downloadCsv } from '@/lib/csv'
import { errorMessage } from '@/lib/errorMessage'
import { formatDateTime } from '@/lib/format'
import { newUlid } from '@/lib/ulid'

export function PlayersPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { tenantId, setTenantId, tenants } = useScopedTenant()
  const query = useAsyncQuery(() => listPlayers(tenantId), [tenantId])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('all')
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [email, setEmail] = useState('')
  const [formTenant, setFormTenant] = useState(tenantId)
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const pageSize = 6

  const tenantName = (id: string) => tenants.find((item) => item.id === id)?.name ?? id

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase()
    return (query.data ?? []).filter((player) => {
      const matchesQuery =
        !needle ||
        player.email.toLowerCase().includes(needle) ||
        player.id.toLowerCase().includes(needle) ||
        tenantName(player.tenantId).toLowerCase().includes(needle)
      const matchesStatus = status === 'all' || player.status === status
      return matchesQuery && matchesStatus
    })
  }, [query.data, search, status, tenants])

  const columns: Column<PlayerResponse>[] = [
    {
      id: 'email',
      header: t('col.player'),
      cell: (row) => (
        <Link to={`/management/players/${row.id}`} style={{ color: 'var(--bt-accent)' }}>
          {row.email}
        </Link>
      ),
    },
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'tenant', header: t('col.tenant'), cell: (row) => tenantName(row.tenantId) },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
    { id: 'createdAt', header: t('col.when'), cell: (row) => formatDateTime(row.createdAt) },
  ]

  const pageRows = filtered.slice((page - 1) * pageSize, page * pageSize)
  const tenantOptions = tenants.length
    ? tenants.map((item) => ({ value: item.id, label: item.name }))
    : [{ value: tenantId, label: tenantId }]

  const exportCsv = () => {
    downloadCsv(
      'players.csv',
      filtered.map((player) => ({
        id: player.id,
        email: player.email,
        tenantId: player.tenantId,
        status: player.status,
        createdAt: player.createdAt,
      })),
    )
    toast.push({ title: t('common.export'), tone: 'success' })
  }

  const submit = async () => {
    const next: Record<string, string> = {}
    if (!email.includes('@')) next.email = t('common.validEmail')
    if (!formTenant) next.tenant = t('common.required')
    setErrors(next)
    if (Object.keys(next).length) return
    setSaving(true)
    try {
      await registerPlayer({
        playerId: newUlid(),
        tenantId: formTenant,
        email: email.trim().toLowerCase(),
      })
      toast.push({ title: t('mgmt.playerRegistered'), tone: 'success' })
      setOpen(false)
      setEmail('')
      if (formTenant === tenantId) await query.reload()
    } catch (error) {
      toast.push({ title: errorMessage(error), tone: 'danger' })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.management') }, { label: t('nav.players') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('mgmt.playersTitle')}</h1>
          <p className="bt-page-subtitle">{t('mgmt.playersSub')}</p>
        </div>
        <div className="bt-page-actions">
          <TenantSelect tenantId={tenantId} tenants={tenants} onChange={setTenantId} label={t('col.tenant')} />
          <Button variant="secondary" onClick={exportCsv}>
            {t('common.export')}
          </Button>
          <Can permission="players.write">
            <Button
              onClick={() => {
                setFormTenant(tenantId)
                setOpen(true)
              }}
            >
              {t('mgmt.registerPlayer')}
            </Button>
          </Can>
        </div>
      </div>
      <Card>
        <div className="bt-table-toolbar">
          <div className="bt-table-toolbar__left">
            <Search
              placeholder={t('common.search')}
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
              style={{ width: 280 }}
            />
            <Select
              options={[
                { value: 'all', label: t('common.all') },
                { value: 'active', label: 'active' },
                { value: 'suspended', label: 'suspended' },
                { value: 'blocked', label: 'blocked' },
                { value: 'self_excluded', label: 'self_excluded' },
              ]}
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
              aria-label={t('common.status')}
            />
          </div>
        </div>
        <AsyncState status={query.status} onRetry={query.reload}>
          {filtered.length === 0 ? (
            <EmptyState title={t('common.emptyTitle')} description={t('common.emptyDesc')} />
          ) : (
            <>
              <Table columns={columns} rows={pageRows} />
              <Pagination page={page} pageSize={pageSize} total={filtered.length} onChange={setPage} />
            </>
          )}
        </AsyncState>
      </Card>
      <Modal
        open={open}
        title={t('mgmt.registerPlayer')}
        onClose={() => setOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setOpen(false)}>
              {t('common.cancel')}
            </Button>
            <Button onClick={() => void submit()} loading={saving}>
              {t('common.create')}
            </Button>
          </>
        }
      >
        <div className="bt-form-grid">
          <Input
            label={t('col.email')}
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={errors.email}
          />
          <Select
            label={t('col.tenant')}
            value={formTenant}
            onChange={(event) => setFormTenant(event.target.value)}
            options={tenantOptions}
            error={errors.tenant}
          />
        </div>
      </Modal>
    </div>
  )
}
