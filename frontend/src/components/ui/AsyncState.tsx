import type { ReactNode } from 'react'
import { Button } from './Button'
import { EmptyState } from './EmptyState'
import { Skeleton } from './Skeleton'
import { useI18n } from '@/i18n/I18nContext'

export type AsyncStatus = 'idle' | 'loading' | 'success' | 'empty' | 'error'

export function AsyncState({
  status,
  onRetry,
  emptyTitle,
  emptyDescription,
  children,
  skeletonRows = 4,
}: {
  status: AsyncStatus
  onRetry?: () => void
  emptyTitle?: string
  emptyDescription?: string
  children: ReactNode
  skeletonRows?: number
}) {
  const { t } = useI18n()

  if (status === 'loading') {
    return (
      <div className="bt-stack" style={{ gap: 10 }} aria-busy="true" aria-live="polite">
        {Array.from({ length: skeletonRows }, (_, index) => (
          <Skeleton key={index} height={36} />
        ))}
      </div>
    )
  }

  if (status === 'error') {
    return (
      <EmptyState
        title={t('common.errorTitle')}
        description={t('common.errorDesc')}
        action={
          onRetry ? (
            <Button variant="secondary" onClick={onRetry}>
              {t('common.retry')}
            </Button>
          ) : undefined
        }
      />
    )
  }

  if (status === 'empty') {
    return (
      <EmptyState
        title={emptyTitle ?? t('common.emptyTitle')}
        description={emptyDescription ?? t('common.emptyDesc')}
      />
    )
  }

  return <>{children}</>
}
