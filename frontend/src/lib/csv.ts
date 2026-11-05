export function toCsv(rows: Array<Record<string, string | number | boolean | null | undefined>>): string {
  if (rows.length === 0) return ''
  const headers = Object.keys(rows[0])
  const escape = (value: string | number | boolean | null | undefined) => {
    const raw = value == null ? '' : String(value)
    if (/[",\n\r]/.test(raw)) return `"${raw.replace(/"/g, '""')}"`
    return raw
  }
  return [
    headers.join(','),
    ...rows.map((row) => headers.map((key) => escape(row[key])).join(',')),
  ].join('\n')
}

export function downloadCsv(filename: string, rows: Array<Record<string, string | number | boolean | null | undefined>>) {
  const blob = new Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}
