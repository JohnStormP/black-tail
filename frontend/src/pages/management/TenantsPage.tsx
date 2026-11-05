import { useState } from 'react'
import { createTenantRequest, getTenantRequest, listTenantsRequest, type TenantResponse } from '@/api/tenantsApi'
import { useAuth } from '@/auth/AuthContext'
import { Can } from '@/components/auth/Can'
import {
  AsyncState,
  Breadcrumb,
  Button,
  Card,
  Input,
  Modal,
  Status,
  Table,
  type Column,
  useToast,
} from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { useI18n } from '@/i18n/I18nContext'
import { downloadCsv } from '@/lib/csv'
import { errorMessage } from '@/lib/errorMessage'
import { newUlid } from '@/lib/ulid'

export function TenantsPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { user } = useAuth()
  const query = useAsyncQuery(() => listTenantsRequest(), [])
  const currentTenantQuery = useAsyncQuery(async () => {
    if (!user?.tenantId) return null
    return getTenantRequest(user.tenantId)
  }, [user?.tenantId])
  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const columns: Column<TenantResponse>[] = [
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'name', header: t('col.tenant'), cell: (row) => row.name },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
  ]

  const rows = query.data ?? []

  const exportCsv = () => {
    downloadCsv(
      'tenants.csv',
      rows.map((row) => ({
        id: row.id,
        name: row.name,
        status: row.status,
      })),
    )
    toast.push({ title: t('common.export'), tone: 'success' })
  }

  const submit = async () => {
    const next: Record<string, string> = {}
    if (name.trim().length < 2) next.name = t('common.required')
    setErrors(next)
    if (Object.keys(next).length) return
    setSaving(true)
    try {
      await createTenantRequest({ tenantId: newUlid(), name: name.trim() })
      toast.push({ title: 'Tenant registered', tone: 'success' })
      setOpen(false)
      setName('')
      await query.reload()
    } catch (error) {
      toast.push({ title: errorMessage(error), tone: 'danger' })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.management') }, { label: t('nav.tenants') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('mgmt.tenantsTitle')}</h1>
          <p className="bt-page-subtitle">{t('mgmt.tenantsSub')}</p>
        </div>
        <div className="bt-page-actions">
          <Button variant="secondary" onClick={exportCsv}>
            {t('common.export')}
          </Button>
          <Can permission="tenants.write">
            <Button onClick={() => setOpen(true)}>{t('form.registerTenant')}</Button>
          </Can>
        </div>
      </div>
      <Card>
        <h2 className="bt-section-title" style={{ marginTop: 0 }}>
          Current tenant
        </h2>
        <AsyncState
          status={currentTenantQuery.status}
          onRetry={currentTenantQuery.reload}
          emptyTitle="No tenant on this session"
          emptyDescription="This user has no tenantId."
          skeletonRows={2}
        >
          {currentTenantQuery.data ? (
            <p className="bt-muted">
              <strong>{currentTenantQuery.data.name}</strong>
              {' · '}
              {currentTenantQuery.data.id}
              {' · '}
              <Status value={currentTenantQuery.data.status} />
            </p>
          ) : null}
        </AsyncState>
      </Card>
      <Card>
        <AsyncState status={query.status} onRetry={query.reload}>
          <Table columns={columns} rows={rows} />
        </AsyncState>
      </Card>
      <Modal
        open={open}
        title={t('form.registerTenant')}
        onClose={() => setOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setOpen(false)}>
              {t('common.cancel')}
            </Button>
            <Button onClick={() => void submit()} loading={saving}>
              {t('common.create')}
            </Button>
          </>
        }
      >
        <div className="bt-form-grid">
          <Input label={t('col.name')} value={name} onChange={(event) => setName(event.target.value)} error={errors.name} />
        </div>
      </Modal>
    </div>
  )
}
