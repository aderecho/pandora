import { ensureCsrfCookie, http } from './http'
import type { ApiEnvelope, User } from '@/types/api'

export async function login(email: string, password: string): Promise<User> {
  await ensureCsrfCookie()
  const { data } = await http.post<ApiEnvelope<{ user: User }>>('/auth/login', { email, password })
  return data.data.user
}

export async function logout(): Promise<void> {
  await http.post('/auth/logout')
}

export async function changePassword(
  currentPassword: string,
  newPassword: string,
): Promise<void> {
  await http.post('/auth/change-password', {
    current_password: currentPassword,
    new_password: newPassword,
    new_password_confirmation: newPassword,
  })
}
