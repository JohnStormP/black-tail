import { Search as SearchIcon } from 'lucide-react'
import { cn } from '@/lib/cn'
import type { InputHTMLAttributes } from 'react'

type Props = InputHTMLAttributes<HTMLInputElement>

export function Search({ className, ...props }: Props) {
  return (
    <div className={cn('bt-search', className)}>
      <SearchIcon className="bt-search__icon" size={16} />
      <input className="bt-input" type="search" {...props} />
    </div>
  )
}
