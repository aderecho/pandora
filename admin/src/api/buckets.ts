import { http } from './http'
import type { ApiEnvelope, Bucket, BucketUsage } from '@/types/api'

export interface BucketCreatePayload {
  name: string
  storage_path?: string
  quota_bytes?: number | null
  max_file_size_bytes?: number | null
  allowed_extensions?: string[] | null
  allowed_mime_types?: string[] | null
  versioning_enabled?: boolean
  public_access?: boolean
}

export type BucketUpdatePayload = Partial<BucketCreatePayload> & { status?: 'active' | 'archived' }

export async function listBuckets(params: { search?: string; status?: string } = {}) {
  const { data } = await http.get<ApiEnvelope<Bucket[]>>('/buckets', { params })
  return data
}

export async function getBucket(name: string) {
  const { data } = await http.get<ApiEnvelope<Bucket> & { meta: { usage: BucketUsage } }>(
    `/buckets/${encodeURIComponent(name)}`,
  )
  return data
}

export async function createBucket(payload: BucketCreatePayload) {
  const { data } = await http.post<ApiEnvelope<Bucket>>('/buckets', payload)
  return data.data
}

export async function updateBucket(name: string, payload: BucketUpdatePayload) {
  const { data } = await http.patch<ApiEnvelope<Bucket>>(`/buckets/${encodeURIComponent(name)}`, payload)
  return data.data
}

export async function deleteBucket(name: string) {
  await http.delete(`/buckets/${encodeURIComponent(name)}`)
}
