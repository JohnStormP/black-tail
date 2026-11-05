import { apiRequest } from './http'

export type PaymentType = 'deposit' | 'withdrawal'

export type PaymentResponse = {
  id: string
  tenantId: string
  playerId: string
  walletId: string
  type: string
  amountMinor: number
  currency: string
  status: string
  idempotencyKey: string
  provider: string
  providerReference: string | null
  failureReason: string | null
  createdAt: string
}

export type InitiateDepositInput = {
  paymentId: string
  playerId: string
  walletId: string
  amountMinor: number
  currency: string
  idempotencyKey: string
  provider?: string
}

export type InitiateWithdrawalInput = InitiateDepositInput & {
  reserveEntryId: string
  releaseEntryId: string
}

export async function listPayments(tenantId: string, type?: PaymentType): Promise<PaymentResponse[]> {
  const params = new URLSearchParams({ tenantId })
  if (type) params.set('type', type)
  return apiRequest<PaymentResponse[]>(`/api/payments?${params.toString()}`, { method: 'GET' })
}

export async function initiateDeposit(input: InitiateDepositInput): Promise<PaymentResponse> {
  const { provider = 'fake', ...rest } = input
  return apiRequest<PaymentResponse>('/api/payments/deposits', {
    method: 'POST',
    body: JSON.stringify({ ...rest, provider }),
  })
}

export async function initiateWithdrawal(input: InitiateWithdrawalInput): Promise<PaymentResponse> {
  const { provider = 'fake', ...rest } = input
  return apiRequest<PaymentResponse>('/api/payments/withdrawals', {
    method: 'POST',
    body: JSON.stringify({ ...rest, provider }),
  })
}
