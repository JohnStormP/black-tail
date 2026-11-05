import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

export type Column<T> = {
  id: string
  header: string
  sortable?: boolean
  align?: 'left' | 'right'
  cell: (row: T) => ReactNode
}

export function Table<T extends { id: string }>({
  columns,
  rows,
  sortId,
  sortDir,
  onSort,
  empty,
}: {
  columns: Column<T>[]
  rows: T[]
  sortId?: string
  sortDir?: 'asc' | 'desc'
  onSort?: (id: string) => void
  empty?: ReactNode
}) {
  if (rows.length === 0 && empty) return <>{empty}</>

  return (
    <div className="bt-table-wrap">
      <table className="bt-table">
        <thead>
          <tr>
            {columns.map((column) => {
              const active = sortId === column.id
              return (
                <th
                  key={column.id}
                  className={cn(column.sortable && 'bt-sortable')}
                  style={{ textAlign: column.align ?? 'left' }}
                  onClick={() => column.sortable && onSort?.(column.id)}
                >
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                    {column.header}
                    {column.sortable ? (
                      active ? (
                        sortDir === 'asc' ? <ArrowUp size={12} /> : <ArrowDown size={12} />
                      ) : (
                        <ArrowUpDown size={12} />
                      )
                    ) : null}
                  </span>
                </th>
              )
            })}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id}>
              {columns.map((column) => (
                <td key={column.id} style={{ textAlign: column.align ?? 'left' }}>
                  {column.cell(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
