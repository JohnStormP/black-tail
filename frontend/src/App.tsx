import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '@/auth/AuthContext'
import { ProtectedRoute } from '@/auth/ProtectedRoute'
import { AppShell } from '@/components/layout/AppShell'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import { ToastProvider } from '@/components/ui'
import { DataProvider } from '@/data/DataStore'
import { I18nProvider } from '@/i18n/I18nContext'
import { LoginPage } from '@/pages/auth/LoginPage'
import { DashboardPage } from '@/pages/dashboard/DashboardPage'
import { AnalyticsOverviewPage } from '@/pages/analytics/AnalyticsOverviewPage'
import { RevenuePage } from '@/pages/analytics/RevenuePage'
import { PlayersAnalyticsPage } from '@/pages/analytics/PlayersAnalyticsPage'
import { TransactionsAnalyticsPage } from '@/pages/analytics/TransactionsAnalyticsPage'
import { DepositsPage } from '@/pages/operations/DepositsPage'
import { WithdrawalsPage } from '@/pages/operations/WithdrawalsPage'
import { PlayersPage } from '@/pages/management/PlayersPage'
import { PlayerDetailPage } from '@/pages/management/PlayerDetailPage'
import { TenantsPage } from '@/pages/management/TenantsPage'
import { GamesCatalogPage } from '@/pages/games/GamesCatalogPage'
import { LuckyReelsPage } from '@/pages/games/LuckyReelsPage'
import { CrashPage } from '@/pages/games/CrashPage'
import { TenantAccessPage } from '@/pages/games/TenantAccessPage'
import { SettingsPage } from '@/pages/system/SettingsPage'

export default function App() {
  return (
    <ErrorBoundary>
      <I18nProvider>
        <AuthProvider>
          <DataProvider>
            <ToastProvider>
              <BrowserRouter>
                <Routes>
                  <Route path="/login" element={<LoginPage />} />
                  <Route element={<ProtectedRoute />}>
                    <Route element={<AppShell />}>
                      <Route index element={<DashboardPage />} />
                      <Route path="analytics" element={<AnalyticsOverviewPage />} />
                      <Route path="analytics/revenue" element={<RevenuePage />} />
                      <Route path="analytics/players" element={<PlayersAnalyticsPage />} />
                      <Route path="analytics/transactions" element={<TransactionsAnalyticsPage />} />
                      <Route path="operations/deposits" element={<DepositsPage />} />
                      <Route path="operations/withdrawals" element={<WithdrawalsPage />} />
                      <Route path="management/players" element={<PlayersPage />} />
                      <Route path="management/players/:playerId" element={<PlayerDetailPage />} />
                      <Route path="management/tenants" element={<TenantsPage />} />
                      <Route path="games" element={<GamesCatalogPage />} />
                      <Route path="games/lucky-reels" element={<LuckyReelsPage />} />
                      <Route path="games/crash" element={<CrashPage />} />
                      <Route path="games/tenant-access" element={<TenantAccessPage />} />
                      <Route path="system/settings" element={<SettingsPage />} />
                      <Route path="*" element={<Navigate to="/" replace />} />
                    </Route>
                  </Route>
                </Routes>
              </BrowserRouter>
            </ToastProvider>
          </DataProvider>
        </AuthProvider>
      </I18nProvider>
    </ErrorBoundary>
  )
}
