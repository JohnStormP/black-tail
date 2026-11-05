import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { listPayments, type PaymentResponse } from '@/api/paymentsApi'
import { blockPlayer, getPlayer, type PlayerResponse } from '@/api/playersApi'
import { listWallets, type WalletResponse } from '@/api/walletsApi'
import { Can } from '@/components/auth/Can'
import {
  Alert,
  AsyncState,
  Breadcrumb,
  Button,
  Card,
  EmptyState,
  Status,
  Table,
  Tabs,
  type Column,
  useToast,
} from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { useI18n } from '@/i18n/I18nContext'
import { errorMessage } from '@/lib/errorMessage'
import { formatDateTime, formatMinor } from '@/lib/format'

type PlayerDetail = {
  player: PlayerResponse
  wallets: WalletResponse[]
  payments: PaymentResponse[]
}

export function PlayerDetailPage() {
  const { playerId } = useParams()
  const toast = useToast()
  const { t } = useI18n()
  const [tab, setTab] = useState('overview')
  const [blocking, setBlocking] = useState(false)
  const query = useAsyncQuery(async (): Promise<PlayerDetail | null> => {
    if (!playerId) return null
    const player = await getPlayer(playerId)
    const [wallets, payments] = await Promise.all([
      listWallets(player.id),
      listPayments(player.tenantId).catch(() => [] as PaymentResponse[]),
    ])
    return {
      player,
      wallets,
      payments: payments.filter((payment) => payment.playerId === player.id),
    }
  }, [playerId])

  const player = query.data?.player
  const wallets = query.data?.wallets ?? []
  const payments = query.data?.payments ?? []
  const sameCurrency = wallets.length > 0 && wallets.every((wallet) => wallet.currency === wallets[0]?.currency)
  const availableMinor = sameCurrency ? wallets.reduce((sum, wallet) => sum + wallet.available, 0) : null
  const reservedMinor = sameCurrency ? wallets.reduce((sum, wallet) => sum + wallet.reserved, 0) : null

  const walletColumns: Column<WalletResponse>[] = [
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'currency', header: t('settings.currency'), cell: (row) => row.currency },
    {
      id: 'available',
      header: 'Available',
      align: 'right',
      cell: (row) => <span className="bt-money">{formatMinor(row.available)}</span>,
    },
    {
      id: 'reserved',
      header: 'Reserved',
      align: 'right',
      cell: (row) => <span className="bt-money">{formatMinor(row.reserved)}</span>,
    },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
  ]

  const paymentColumns: Column<PaymentResponse>[] = [
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'type', header: t('col.type'), cell: (row) => row.type },
    { id: 'provider', header: t('col.method'), cell: (row) => row.provider },
    {
      id: 'amount',
      header: t('col.amount'),
      align: 'right',
      cell: (row) => <span className="bt-money">{formatMinor(row.amountMinor)}</span>,
    },
    { id: 'currency', header: t('settings.currency'), cell: (row) => row.currency },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
    { id: 'createdAt', header: t('col.when'), cell: (row) => formatDateTime(row.createdAt) },
  ]

  const onBlock = async () => {
    if (!player) return
    setBlocking(true)
    try {
      await blockPlayer(player.id)
      toast.push({ title: 'Player blocked', tone: 'danger' })
      await query.reload()
    } catch (error) {
      toast.push({ title: errorMessage(error), tone: 'danger' })
    } finally {
      setBlocking(false)
    }
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb
        items={[
          { label: t('group.management'), to: '/management/players' },
          { label: t('nav.players'), to: '/management/players' },
          { label: player?.email ?? playerId ?? t('nav.players') },
        ]}
      />
      <AsyncState
        status={query.status}
        onRetry={query.reload}
        emptyTitle={t('common.emptyTitle')}
        emptyDescription={t('common.emptyDesc')}
        skeletonRows={6}
      >
        {player ? (
          <div className="bt-stack">
            <div className="bt-page-header">
              <div>
                <h1 className="bt-page-title">{player.email}</h1>
                <p className="bt-page-subtitle" style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                  <span className="bt-numeric">{player.tenantId}</span>
                  <Status value={player.status} />
                </p>
              </div>
              <div className="bt-page-actions">
                <Can permission="players.write">
                  <Button
                    variant="danger"
                    onClick={() => void onBlock()}
                    loading={blocking}
                    disabled={player.status === 'blocked'}
                  >
                    Block
                  </Button>
                </Can>
              </div>
            </div>
            {player.status !== 'active' ? (
              <Alert tone="warning" title={t('common.status')}>
                This player cannot place new bets until status is active.
              </Alert>
            ) : null}
            <Tabs
              items={[
                { id: 'overview', label: t('group.overview') },
                { id: 'wallet', label: t('common.balance') },
                { id: 'activity', label: t('nav.transactions') },
              ]}
              value={tab}
              onChange={setTab}
            />
            {tab === 'overview' && (
              <div className="bt-grid-3">
                <Card title="Available">
                  <p className="bt-stat__value bt-numeric" style={{ margin: 0 }}>
                    {availableMinor == null ? '-' : formatMinor(availableMinor)}
                  </p>
                  <p className="bt-muted">{sameCurrency ? wallets[0]?.currency : 'Mixed currencies'}</p>
                </Card>
                <Card title="Reserved">
                  <p className="bt-stat__value bt-numeric" style={{ margin: 0 }}>
                    {reservedMinor == null ? '-' : formatMinor(reservedMinor)}
                  </p>
                </Card>
                <Card title={t('col.when')}>
                  <p className="bt-stat__value" style={{ margin: 0, fontSize: '1.1rem' }}>
                    {formatDateTime(player.createdAt)}
                  </p>
                </Card>
              </div>
            )}
            {tab === 'wallet' && (
              <Card title={t('common.balance')}>
                {wallets.length === 0 ? (
                  <EmptyState title={t('common.emptyTitle')} description={t('common.emptyDesc')} />
                ) : (
                  <Table columns={walletColumns} rows={wallets} />
                )}
              </Card>
            )}
            {tab === 'activity' && (
              <Card title={t('dashboard.latestTx')}>
                {payments.length === 0 ? (
                  <EmptyState title={t('common.emptyTitle')} description={t('common.emptyDesc')} />
                ) : (
                  <Table columns={paymentColumns} rows={payments} />
                )}
              </Card>
            )}
          </div>
        ) : null}
      </AsyncState>
    </div>
  )
}
