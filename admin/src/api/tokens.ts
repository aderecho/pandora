import { http } from './http'
import type { ApiEnvelope, ApiToken } from '@/types/api'

export interface TokenBucketScopeInput {
  bucket: string
  path_prefix?: string | null
  permissions: string[]
}

export interface TokenCreatePayload {
  name: string
  user_id?: number
  abilities: string[]
  expires_at?: string | null
  allowed_ips?: string[] | null
  bucket_scopes?: TokenBucketScopeInput[]
}

export type TokenUpdatePayload = Partial<TokenCreatePayload>

export async function listTokens() {
  const { data } = await http.get<ApiEnvelope<ApiToken[]>>('/tokens')
  return data
}

export async function createToken(payload: TokenCreatePayload) {
  const { data } = await http.post<ApiEnvelope<{ token: ApiToken; raw_token: string }>>('/tokens', payload)
  return data.data
}

export async function updateToken(id: number, payload: TokenUpdatePayload) {
  const { data } = await http.patch<ApiEnvelope<ApiToken>>(`/tokens/${id}`, payload)
  return data.data
}

export async function rotateToken(id: number) {
  const { data } = await http.post<ApiEnvelope<{ token: ApiToken; raw_token: string }>>(`/tokens/${id}/rotate`)
  return data.data
}

export async function revokeToken(id: number) {
  await http.post(`/tokens/${id}/revoke`)
}
