export class ApiError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(status: number, message: string, body: unknown = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

type Json = Record<string, unknown> | unknown[] | string | number | boolean | null

export async function apiRequest<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  const response = await fetch(path, {
    ...init,
    headers,
    credentials: 'include',
  })

  const text = await response.text()
  let body: Json = null
  if (text !== '') {
    try {
      body = JSON.parse(text) as Json
    } catch {
      body = text
    }
  }

  if (!response.ok) {
    const message =
      typeof body === 'object' &&
      body !== null &&
      'message' in body &&
      typeof (body as { message: unknown }).message === 'string'
        ? (body as { message: string }).message
        : typeof body === 'object' &&
            body !== null &&
            'error' in body &&
            typeof (body as { error: unknown }).error === 'string'
          ? (body as { error: string }).error
          : `HTTP ${response.status}`
    throw new ApiError(response.status, message, body)
  }

  return body as T
}
