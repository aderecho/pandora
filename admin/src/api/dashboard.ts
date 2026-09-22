import { http } from './http'
import type { ApiEnvelope, DashboardMetrics } from '@/types/api'

export async function getDashboardMetrics() {
  const { data } = await http.get<ApiEnvelope<DashboardMetrics>>('/admin/dashboard/metrics')
  return data.data
}
