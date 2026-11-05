import { useMemo, useState } from 'react'
import { listPayments, type PaymentResponse } from '@/api/paymentsApi'
import {
  AsyncState,
  Breadcrumb,
  Button,
  Card,
  DatePicker,
  EmptyState,
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
import { inDayRange } from '@/lib/analyticsView'
import { downloadCsv } from '@/lib/csv'
import { recentDates } from '@/lib/date-range'
import { formatDateTime, formatMinor } from '@/lib/format'

export function TransactionsAnalyticsPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { tenantId, setTenantId, tenants } = useScopedTenant()
  const query = useAsyncQuery(() => listPayments(tenantId), [tenantId])
  const [search, setSearch] = useState('')
  const [type, setType] = useState('all')
  const [from, setFrom] = useState(() => recentDates(14).from)
  const [to, setTo] = useState(() => recentDates(14).to)
  const [page, setPage] = useState(1)
  const [sortId, setSortId] = useState('createdAt')
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc')
  const pageSize = 5

  const columns: Column<PaymentResponse>[] = [
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'playerId', header: t('col.player'), cell: (row) => <span className="bt-numeric">{row.playerId}</span> },
    { id: 'type', header: t('col.type'), cell: (row) => row.type },
    { id: 'provider', header: t('col.method'), cell: (row) => row.provider },
    {
      id: 'amountMinor',
      header: t('col.amount'),
      align: 'right',
      sortable: true,
      cell: (row) => <span className="bt-money">{formatMinor(row.amountMinor)}</span>,
    },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
    { id: 'createdAt', header: t('col.when'), sortable: true, cell: (row) => formatDateTime(row.createdAt) },
  ]

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase()
    const rows = (query.data ?? []).filter((row) => {
      const matchesQuery =
        !needle ||
        row.id.toLowerCase().includes(needle) ||
        row.playerId.toLowerCase().includes(needle) ||
        row.provider.toLowerCase().includes(needle)
      const matchesType = type === 'all' || row.type === type
      return matchesQuery && matchesType && inDayRange(row.createdAt, from, to)
    })
    return [...rows].sort((a, b) => {
      const left = a[sortId as keyof PaymentResponse]
      const right = b[sortId as keyof PaymentResponse]
      if (typeof left === 'number' && typeof right === 'number') return sortDir === 'asc' ? left - right : right - left
      return sortDir === 'asc'
        ? String(left).localeCompare(String(right))
        : String(right).localeCompare(String(left))
    })
  }, [query.data, search, type, from, to, sortId, sortDir])

  const pageRows = filtered.slice((page - 1) * pageSize, page * pageSize)

  const exportCsv = () => {
    downloadCsv(
      'transactions-analytics.csv',
      filtered.map((row) => ({
        id: row.id,
        playerId: row.playerId,
        type: row.type,
        provider: row.provider,
        amountMinor: row.amountMinor,
        currency: row.currency,
        status: row.status,
        createdAt: row.createdAt,
      })),
    )
    toast.push({ title: t('common.export'), tone: 'success' })
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb
        items={[
          { label: t('group.analytics'), to: '/analytics' },
          { label: t('nav.transactions') },
        ]}
      />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('analytics.txTitle')}</h1>
          <p className="bt-page-subtitle">{t('analytics.txSub')}</p>
        </div>
        <div className="bt-page-actions">
          <TenantSelect tenantId={tenantId} tenants={tenants} onChange={setTenantId} label={t('col.tenant')} />
          <DatePicker
            label=""
            aria-label={t('analytics.from')}
            value={from}
            onChange={(event) => {
              setFrom(event.target.value)
              setPage(1)
            }}
          />
          <DatePicker
            label=""
            aria-label={t('analytics.to')}
            value={to}
            onChange={(event) => {
              setTo(event.target.value)
              setPage(1)
            }}
          />
          <Button variant="secondary" onClick={exportCsv}>
            {t('common.export')}
          </Button>
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
              style={{ width: 260 }}
            />
            <Select
              options={[
                { value: 'all', label: t('common.all') },
                { value: 'deposit', label: t('nav.deposits') },
                { value: 'withdrawal', label: t('nav.withdrawals') },
              ]}
              value={type}
              onChange={(event) => {
                setType(event.target.value)
                setPage(1)
              }}
              aria-label={t('col.type')}
            />
          </div>
        </div>
        <AsyncState status={query.status} onRetry={query.reload}>
          {filtered.length === 0 ? (
            <EmptyState title={t('common.emptyTitle')} description={t('common.emptyDesc')} />
          ) : (
            <>
              <Table
                columns={columns}
                rows={pageRows}
                sortId={sortId}
                sortDir={sortDir}
                onSort={(id) => {
                  if (sortId === id) setSortDir((direction) => (direction === 'asc' ? 'desc' : 'asc'))
                  else {
                    setSortId(id)
                    setSortDir('desc')
                  }
                }}
              />
              <Pagination page={page} pageSize={pageSize} total={filtered.length} onChange={setPage} />
            </>
          )}
        </AsyncState>
      </Card>
    </div>
  )
}
