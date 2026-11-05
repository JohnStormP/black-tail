import { describe, expect, it } from 'vitest'
import { toCsv } from './csv'

describe('toCsv', () => {
  it('escapes commas and quotes', () => {
    const csv = toCsv([
      { name: 'A, B', note: 'say "hi"' },
      { name: 'plain', note: 'ok' },
    ])
    expect(csv).toBe('name,note\n"A, B","say ""hi"""\nplain,ok')
  })

  it('returns empty string for empty rows', () => {
    expect(toCsv([])).toBe('')
  })
})
