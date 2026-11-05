import { useMemo, useState } from 'react'
import { listPlayers, type PlayerResponse } from '@/api/playersApi'
import type { InitiateDepositInput } from '@/api/paymentsApi'
import { listWallets, type WalletResponse } from '@/api/walletsApi'
import { Input, Select } from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { useI18n } from '@/i18n/I18nContext'
import { PLAYER_ID, WALLET_ID } from '@/lib/demoIds'
import { formatMinor } from '@/lib/format'
import { newUlid } from '@/lib/ulid'

const CURRENCIES = ['USD', 'EUR', 'UAH'] as const

export function parseAmountMinor(raw: string): number | null {
  const major = Number(raw)
  if (!Number.isFinite(major) || major <= 0) return null
  const minor = Math.round(major * 100)
  return minor > 0 ? minor : null
}

function withFallback(
  options: { value: string; label: string }[],
  id: string,
  label: string,
): { value: string; label: string }[] {
  if (options.some((option) => option.value === id)) return options
  return [{ value: id, label }, ...options]
}

export function usePaymentDraft(tenantId: string) {
  const { t } = useI18n()
  const [playerId, setPlayerId] = useState(PLAYER_ID)
  const [walletId, setWalletId] = useState(WALLET_ID)
  const [amount, setAmount] = useState('')
  const [currency, setCurrency] = useState<(typeof CURRENCIES)[number]>('USD')
  const [errors, setErrors] = useState<Record<string, string>>({})

  const playersQuery = useAsyncQuery(async () => {
    try {
      return await listPlayers(tenantId)
    } catch {
      return [] as PlayerResponse[]
    }
  }, [tenantId])

  const walletsQuery = useAsyncQuery(async () => {
    try {
      return await listWallets(playerId)
    } catch {
      return [] as WalletResponse[]
    }
  }, [playerId])

  const playerOptions = useMemo(
    () =>
      withFallback(
        (playersQuery.data ?? []).map((player) => ({ value: player.id, label: player.email })),
        PLAYER_ID,
        'Demo player',
      ),
    [playersQuery.data],
  )

  const walletOptions = useMemo(
    () =>
      withFallback(
        (walletsQuery.data ?? []).map((wallet) => ({
          value: wallet.id,
          label: `${wallet.currency} ${formatMinor(wallet.available)} available`,
        })),
        WALLET_ID,
        'Demo wallet',
      ),
    [walletsQuery.data],
  )

  const build = (): InitiateDepositInput | null => {
    const next: Record<string, string> = {}
    const amountMinor = parseAmountMinor(amount)
    if (amountMinor == null) next.amount = t('common.required')
    if (!playerId) next.playerId = t('common.required')
    if (!walletId) next.walletId = t('common.required')
    setErrors(next)
    if (amountMinor == null || Object.keys(next).length > 0) return null
    return {
      paymentId: newUlid(),
      idempotencyKey: newUlid(),
      playerId,
      walletId,
      amountMinor,
      currency,
      provider: 'fake',
    }
  }

  const reset = () => {
    setAmount('')
    setErrors({})
  }

  const fields = (
    <div className="bt-form-grid">
      <Select
        label={t('col.player')}
        value={playerId}
        onChange={(event) => {
          setPlayerId(event.target.value)
          setWalletId(WALLET_ID)
        }}
        options={playerOptions}
        error={errors.playerId}
      />
      <Select
        label="Wallet"
        value={walletId}
        onChange={(event) => setWalletId(event.target.value)}
        options={walletOptions}
        error={errors.walletId}
      />
      <Input
        label={t('col.amount')}
        type="number"
        min="0.01"
        step="0.01"
        value={amount}
        onChange={(event) => setAmount(event.target.value)}
        error={errors.amount}
        hint={currency}
      />
      <Select
        label={t('settings.currency')}
        value={currency}
        onChange={(event) => setCurrency(event.target.value as (typeof CURRENCIES)[number])}
        options={CURRENCIES.map((code) => ({ value: code, label: code }))}
      />
    </div>
  )

  return { fields, build, reset }
}
