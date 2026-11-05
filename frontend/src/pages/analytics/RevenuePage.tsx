import { useState } from 'react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { getGgrByDay } from '@/api/analyticsApi'
import { ChartContainer, chartTheme } from '@/components/charts/ChartContainer'
import { AsyncState, Breadcrumb, Card, DatePicker, Stat } from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { TenantSelect, useScopedTenant } from '@/hooks/useScopedTenant'
import { useI18n } from '@/i18n/I18nContext'
import { chartTooltipStyle, sumGgr, toGgrPoints } from '@/lib/analyticsView'
import { recentDates } from '@/lib/date-range'
import { formatMinor } from '@/lib/format'

export function RevenuePage() {
  const { t } = useI18n()
  const { tenantId, setTenantId, tenants } = useScopedTenant()
  const [from, setFrom] = useState(() => recentDates(14).from)
  const [to, setTo] = useState(() => recentDates(14).to)
  const query = useAsyncQuery(async () => {
    const ggr = await getGgrByDay({ from, to, tenantId })
    return { series: toGgrPoints(ggr), totals: sumGgr(ggr) }
  }, [tenantId, from, to])
  const data = query.data

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb
        items={[
          { label: t('group.analytics'), to: '/analytics' },
          { label: t('nav.revenue') },
        ]}
      />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('analytics.revenueTitle')}</h1>
          <p className="bt-page-subtitle">{t('analytics.revenueSub')}</p>
        </div>
        <div className="bt-page-actions">
          <TenantSelect tenantId={tenantId} tenants={tenants} onChange={setTenantId} label={t('col.tenant')} />
          <DatePicker
            label=""
            aria-label={t('analytics.from')}
            value={from}
            onChange={(event) => setFrom(event.target.value)}
          />
          <DatePicker
            label=""
            aria-label={t('analytics.to')}
            value={to}
            onChange={(event) => setTo(event.target.value)}
          />
        </div>
      </div>
      <AsyncState status={query.status} onRetry={query.reload} skeletonRows={5}>
        {data ? (
          <div className="bt-stack">
            <div className="bt-grid-3">
              <Stat label="GGR" value={formatMinor(data.totals.ggrMinor)} />
              <Stat label="Stake" value={formatMinor(data.totals.stakeMinor)} />
              <Stat label="Payout" value={formatMinor(data.totals.payoutMinor)} />
            </div>
            <Card title={t('nav.revenue')}>
              <ChartContainer>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.series}>
                    <CartesianGrid stroke={chartTheme.grid} vertical={false} />
                    <XAxis dataKey="day" stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                    <YAxis stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                    <RechartsTooltip contentStyle={chartTooltipStyle} />
                    <Legend />
                    <Bar dataKey="revenue" name="GGR" fill={chartTheme.series[0]} radius={[3, 3, 0, 0]} />
                    <Bar dataKey="stake" name="Stake" fill={chartTheme.series[1]} radius={[3, 3, 0, 0]} />
                    <Bar dataKey="payout" name="Payout" fill={chartTheme.series[4]} radius={[3, 3, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </ChartContainer>
            </Card>
          </div>
        ) : null}
      </AsyncState>
    </div>
  )
}
