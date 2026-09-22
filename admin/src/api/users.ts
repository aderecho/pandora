import { http } from './http'
import type { ApiEnvelope, Role, User } from '@/types/api'

export interface UserCreatePayload {
  name: string
  email: string
  role_id: number
  password?: string
}

export interface UserUpdatePayload {
  name?: string
  email?: string
  role_id?: number
  status?: 'active' | 'disabled'
}

export async function listUsers(params: { search?: string; status?: string } = {}) {
  const { data } = await http.get<ApiEnvelope<User[]>>('/admin/users', { params })
  return data
}

export async function createUser(payload: UserCreatePayload) {
  const { data } = await http.post<ApiEnvelope<{ user: User; generated_password: string | null }>>(
    '/admin/users',
    payload,
  )
  return data.data
}

export async function updateUser(id: number, payload: UserUpdatePayload) {
  const { data } = await http.patch<ApiEnvelope<User>>(`/admin/users/${id}`, payload)
  return data.data
}

export async function deleteUser(id: number) {
  await http.delete(`/admin/users/${id}`)
}

export async function forcePasswordReset(id: number) {
  const { data } = await http.post<ApiEnvelope<{ generated_password: string }>>(
    `/admin/users/${id}/force-password-reset`,
  )
  return data.data
}

export async function listRoles() {
  const { data } = await http.get<ApiEnvelope<Role[]>>('/admin/roles')
  return data.data
}
