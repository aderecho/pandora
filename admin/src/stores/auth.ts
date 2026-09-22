import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { http } from '@/api/http'
import { login as apiLogin, logout as apiLogout } from '@/api/auth'
import type { User } from '@/types/api'

// Mirrors the backend's seeded role -> permission matrix (RolePermissionSeeder)
// closely enough to gate dashboard navigation; the API remains the source of
// truth and re-checks every request server-side regardless of what's shown here.
const ADMIN_TIER = ['super_admin', 'storage_admin']
const AUDIT_READERS = ['super_admin', 'storage_admin', 'read_only_auditor']

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const roleSlug = computed(() => user.value?.role?.slug ?? '')
  const isAdminTier = computed(() => ADMIN_TIER.includes(roleSlug.value))
  const canReadAudit = computed(() => AUDIT_READERS.includes(roleSlug.value))
  const canManageUsers = computed(() => isAdminTier.value)

  async function restore(): Promise<void> {
    try {
      const { data } = await http.get<{ data: { user: User } }>('/auth/me')
      user.value = data.data.user
    } catch {
      user.value = null
    } finally {
      initialized.value = true
    }
  }

  async function login(email: string, password: string): Promise<void> {
    user.value = await apiLogin(email, password)
  }

  async function logout(): Promise<void> {
    try {
      await apiLogout()
    } finally {
      user.value = null
    }
  }

  return {
    user,
    initialized,
    isAuthenticated,
    roleSlug,
    isAdminTier,
    canReadAudit,
    canManageUsers,
    restore,
    login,
    logout,
  }
})
