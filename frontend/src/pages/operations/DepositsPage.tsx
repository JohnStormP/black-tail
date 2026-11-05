import { useMemo, useState } from 'react'
import { initiateDeposit, listPayments, type PaymentResponse } from '@/api/paymentsApi'
import {
  AsyncState,
  Breadcrumb,
  Button,
  Card,
  EmptyState,
  Modal,
  Pagination,
  Search,
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
import { formatDateTime, formatMinor } from '@/lib/format'
import { usePaymentDraft } from '@/pages/operations/paymentDraft'

export function DepositsPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { tenantId, setTenantId, tenants } = useScopedTenant()
  const query = useAsyncQuery(() => listPayments(tenantId, 'deposit'), [tenantId])
  const draft = usePaymentDraft(tenantId)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const pageSize = 5

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase()
    return (query.data ?? []).filter((row) => {
      if (!needle) return true
      return row.id.toLowerCase().includes(needle) || row.playerId.toLowerCase().includes(needle)
    })
  }, [query.data, search])

  const columns: Column<PaymentResponse>[] = [
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'player', header: t('col.player'), cell: (row) => <span className="bt-numeric">{row.playerId}</span> },
    { id: 'wallet', header: 'Wallet', cell: (row) => <span className="bt-numeric">{row.walletId}</span> },
    { id: 'method', header: t('col.method'), cell: (row) => row.provider },
    {
      id: 'amount',
      header: t('col.amount'),
      align: 'right',
      cell: (row) => <span className="bt-money bt-money--pos">{formatMinor(row.amountMinor)}</span>,
    },
    { id: 'currency', header: t('settings.currency'), cell: (row) => row.currency },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
    { id: 'createdAt', header: t('col.when'), cell: (row) => formatDateTime(row.createdAt) },
  ]

  const pageRows = filtered.slice((page - 1) * pageSize, page * pageSize)

  const exportCsv = () => {
    downloadCsv(
      'deposits.csv',
      filtered.map((row) => ({
        id: row.id,
        playerId: row.playerId,
        walletId: row.walletId,
        provider: row.provider,
        amountMinor: row.amountMinor,
        currency: row.currency,
        status: row.status,
        createdAt: row.createdAt,
      })),
    )
    toast.push({ title: t('common.export'), tone: 'success' })
  }

  const submit = async () => {
    const input = draft.build()
    if (!input) return
    setSaving(true)
    try {
      await initiateDeposit(input)
      toast.push({ title: 'Deposit initiated', tone: 'success' })
      setOpen(false)
      draft.reset()
      await query.reload()
    } catch (error) {
      toast.push({ title: errorMessage(error), tone: 'danger' })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.operations') }, { label: t('nav.deposits') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('ops.depositsTitle')}</h1>
          <p className="bt-page-subtitle">{t('ops.depositsSub')}</p>
        </div>
        <div className="bt-page-actions">
          <TenantSelect tenantId={tenantId} tenants={tenants} onChange={setTenantId} label={t('col.tenant')} />
          <Button variant="secondary" onClick={exportCsv}>
            {t('common.export')}
          </Button>
          <Button onClick={() => setOpen(true)}>Initiate deposit</Button>
        </div>
      </div>
      <Card>
        <div className="bt-table-toolbar">
          <Search
            placeholder={t('common.search')}
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
            style={{ width: 260 }}
          />
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
        title="Initiate deposit"
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
        {draft.fields}
      </Modal>
    </div>
  )
}
