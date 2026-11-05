import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { LoadingState } from '@/components/ui'
import { useAuth } from './AuthContext'

export function ProtectedRoute() {
  const { user, loading, canPath } = useAuth()
  const location = useLocation()

  if (loading) return <LoadingState label="Checking session…" />
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  if (!canPath(location.pathname)) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}
