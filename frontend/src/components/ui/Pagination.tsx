import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/lib/cn'
import { useI18n } from '@/i18n/I18nContext'

export function Pagination({
  page,
  pageSize,
  total,
  onChange,
}: {
  page: number
  pageSize: number
  total: number
  onChange: (page: number) => void
}) {
  const { t } = useI18n()
  const pageCount = Math.max(1, Math.ceil(total / pageSize))
  const from = total === 0 ? 0 : (page - 1) * pageSize + 1
  const to = Math.min(total, page * pageSize)

  return (
    <div className="bt-pagination">
      <span>
        {from}-{to} {t('common.of')} {total}
      </span>
      <div className="bt-pagination__pages">
        <button
          type="button"
          className="bt-page-btn"
          disabled={page <= 1}
          onClick={() => onChange(page - 1)}
          aria-label={t('common.prevPage')}
        >
          <ChevronLeft size={14} />
        </button>
        {Array.from({ length: pageCount }, (_, index) => index + 1)
          .slice(0, 6)
          .map((entry) => (
            <button
              key={entry}
              type="button"
              className={cn('bt-page-btn', entry === page && 'bt-page-btn--active')}
              onClick={() => onChange(entry)}
            >
              {entry}
            </button>
          ))}
        <button
          type="button"
          className="bt-page-btn"
          disabled={page >= pageCount}
          onClick={() => onChange(page + 1)}
          aria-label={t('common.nextPage')}
        >
          <ChevronRight size={14} />
        </button>
      </div>
    </div>
  )
}
