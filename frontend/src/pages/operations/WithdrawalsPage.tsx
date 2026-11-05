import { useMemo, useState } from 'react'
import { initiateWithdrawal, listPayments, type PaymentResponse } from '@/api/paymentsApi'
import {
  AsyncState,
  Breadcrumb,
  Button,
  Card,
  EmptyState,
  Modal,
  Pagination,
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
import { newUlid } from '@/lib/ulid'
import { usePaymentDraft } from '@/pages/operations/paymentDraft'

export function WithdrawalsPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { tenantId, setTenantId, tenants } = useScopedTenant()
  const query = useAsyncQuery(() => listPayments(tenantId, 'withdrawal'), [tenantId])
  const draft = usePaymentDraft(tenantId)
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const pageSize = 5
  const rows = query.data ?? []
  const pageRows = rows.slice((page - 1) * pageSize, page * pageSize)

  const columns: Column<PaymentResponse>[] = useMemo(
    () => [
      { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
      { id: 'player', header: t('col.player'), cell: (row) => <span className="bt-numeric">{row.playerId}</span> },
      { id: 'wallet', header: 'Wallet', cell: (row) => <span className="bt-numeric">{row.walletId}</span> },
      { id: 'method', header: t('col.method'), cell: (row) => row.provider },
      {
        id: 'amount',
        header: t('col.amount'),
        align: 'right',
        cell: (row) => <span className="bt-money bt-money--neg">-{formatMinor(row.amountMinor)}</span>,
      },
      { id: 'currency', header: t('settings.currency'), cell: (row) => row.currency },
      { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
      { id: 'createdAt', header: t('col.when'), cell: (row) => formatDateTime(row.createdAt) },
    ],
    [t],
  )

  const exportCsv = () => {
    downloadCsv(
      'withdrawals.csv',
      rows.map((row) => ({
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
      await initiateWithdrawal({
        ...input,
        reserveEntryId: newUlid(),
        releaseEntryId: newUlid(),
      })
      toast.push({ title: 'Withdrawal initiated', tone: 'success' })
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
      <Breadcrumb items={[{ label: t('group.operations') }, { label: t('nav.withdrawals') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('ops.withdrawalsTitle')}</h1>
          <p className="bt-page-subtitle">{t('ops.withdrawalsSub')}</p>
        </div>
        <div className="bt-page-actions">
          <TenantSelect tenantId={tenantId} tenants={tenants} onChange={setTenantId} label={t('col.tenant')} />
          <Button variant="secondary" onClick={exportCsv}>
            {t('common.export')}
          </Button>
          <Button onClick={() => setOpen(true)}>Initiate withdrawal</Button>
        </div>
      </div>
      <Card>
        <AsyncState status={query.status} onRetry={query.reload}>
          {rows.length === 0 ? (
            <EmptyState title={t('common.emptyTitle')} description={t('common.emptyDesc')} />
          ) : (
            <>
              <Table columns={columns} rows={pageRows} />
              <Pagination page={page} pageSize={pageSize} total={rows.length} onChange={setPage} />
            </>
          )}
        </AsyncState>
      </Card>
      <Modal
        open={open}
        title="Initiate withdrawal"
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
