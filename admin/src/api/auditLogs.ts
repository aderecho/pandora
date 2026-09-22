import { http } from './http'
import type { ApiEnvelope, AuditLog } from '@/types/api'

export interface AuditLogFilters {
  actor_type?: string
  action?: string
  status?: string
  bucket_id?: number
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export async function listAuditLogs(params: AuditLogFilters = {}) {
  const { data } = await http.get<ApiEnvelope<AuditLog[]>>('/audit-logs', { params })
  return data
}

export function auditLogExportUrl(params: AuditLogFilters = {}): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      query.set(key, String(value))
    }
  })
  const base = (import.meta.env.VITE_API_BASE_URL as string) ?? 'http://localhost:8000/api/v1'
  const qs = query.toString()
  return `${base}/audit-logs/export${qs ? `?${qs}` : ''}`
}
