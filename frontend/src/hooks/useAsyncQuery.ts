import { useCallback, useEffect, useRef, useState } from 'react'
import type { AsyncStatus } from '@/components/ui/AsyncState'

export function useAsyncQuery<T>(loader: () => Promise<T>, deps: unknown[] = []) {
  const [status, setStatus] = useState<AsyncStatus>('loading')
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<Error | null>(null)
  const loaderRef = useRef(loader)
  loaderRef.current = loader

  const run = useCallback(async () => {
    setStatus('loading')
    setError(null)
    try {
      await new Promise((resolve) => setTimeout(resolve, 350))
      const result = await loaderRef.current()
      const isEmpty = Array.isArray(result) ? result.length === 0 : result == null
      setData(result)
      setStatus(isEmpty ? 'empty' : 'success')
    } catch (err) {
      setError(err instanceof Error ? err : new Error('Unknown error'))
      setStatus('error')
    }
  }, deps)

  useEffect(() => {
    void run()
  }, [run])

  return { status, data, error, reload: run }
}
