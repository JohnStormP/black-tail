import { useMemo, useState } from 'react'

export function usePagedList<T>(items: T[], pageSize = 8) {
  const [page, setPage] = useState(1)
  const total = items.length
  const pageCount = Math.max(1, Math.ceil(total / pageSize))
  const safePage = Math.min(page, pageCount)
  const rows = useMemo(
    () => items.slice((safePage - 1) * pageSize, safePage * pageSize),
    [items, safePage, pageSize],
  )

  return {
    page: safePage,
    setPage,
    pageSize,
    total,
    rows,
    resetPage: () => setPage(1),
  }
}
