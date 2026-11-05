import { X } from 'lucide-react'
import { useEffect, useRef, type ReactNode } from 'react'
import { useFocusTrap } from '@/hooks/useFocusTrap'
import { useI18n } from '@/i18n/I18nContext'
import { IconButton } from './IconButton'

export function Drawer({
  open,
  title,
  children,
  footer,
  onClose,
}: {
  open: boolean
  title: string
  children: ReactNode
  footer?: ReactNode
  onClose: () => void
}) {
  const { t } = useI18n()
  const panelRef = useRef<HTMLElement>(null)
  useFocusTrap(open, panelRef)

  useEffect(() => {
    if (!open) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    const prevOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      window.removeEventListener('keydown', onKey)
      document.body.style.overflow = prevOverflow
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <>
      <div className="bt-overlay bt-drawer-overlay" onClick={onClose} aria-hidden />
      <aside
        ref={panelRef}
        className="bt-drawer"
        role="dialog"
        aria-modal="true"
        aria-label={title}
      >
        <header className="bt-drawer__header">
          <h3 className="bt-panel__title">{title}</h3>
          <IconButton label={t('common.close')} onClick={onClose}>
            <X size={16} />
          </IconButton>
        </header>
        <div className="bt-drawer__body" style={{ flex: 1, overflow: 'auto' }}>
          {children}
        </div>
        {footer ? <footer className="bt-drawer__footer">{footer}</footer> : null}
      </aside>
    </>
  )
}
