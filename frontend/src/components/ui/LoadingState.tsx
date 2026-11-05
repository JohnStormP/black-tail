export function LoadingState({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="bt-loading">
      <span className="bt-spinner" />
      <span>{label}</span>
    </div>
  )
}
