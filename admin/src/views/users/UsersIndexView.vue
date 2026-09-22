<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Badge from '@/components/ui/Badge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import UserFormModal from '@/components/users/UserFormModal.vue'
import GeneratedPasswordModal from '@/components/users/GeneratedPasswordModal.vue'
import {
  createUser,
  deleteUser,
  forcePasswordReset,
  listUsers,
  updateUser,
  type UserCreatePayload,
  type UserUpdatePayload,
} from '@/api/users'
import { useToastStore } from '@/stores/toast'
import { errorMessage, formatDate } from '@/support/format'
import type { User } from '@/types/api'

const toast = useToastStore()
const users = ref<User[]>([])
const loading = ref(true)
const search = ref('')

const formOpen = ref(false)
const editingUser = ref<User | null>(null)
const passwordModalOpen = ref(false)
const generatedPassword = ref<string | null>(null)

const confirmOpen = ref(false)
const confirmMessage = ref('')
let pendingUserId: number | null = null

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await listUsers({ search: search.value || undefined })
    users.value = response.data
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  editingUser.value = null
  formOpen.value = true
}

function openEdit(user: User): void {
  editingUser.value = user
  formOpen.value = true
}

async function handleSubmit(payload: UserCreatePayload | UserUpdatePayload): Promise<void> {
  try {
    if (editingUser.value) {
      await updateUser(editingUser.value.id, payload as UserUpdatePayload)
      toast.success('User updated.')
    } else {
      const result = await createUser(payload as UserCreatePayload)
      toast.success('User created.')
      if (result.generated_password) {
        generatedPassword.value = result.generated_password
        passwordModalOpen.value = true
      }
    }
    formOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

async function handleForceReset(user: User): Promise<void> {
  try {
    const result = await forcePasswordReset(user.id)
    generatedPassword.value = result.generated_password
    passwordModalOpen.value = true
    toast.success('Password reset.')
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

function confirmDelete(user: User): void {
  confirmMessage.value = `Delete ${user.name}? This cannot be undone.`
  pendingUserId = user.id
  confirmOpen.value = true
}

async function handleConfirmDelete(): Promise<void> {
  confirmOpen.value = false
  if (pendingUserId === null) return
  try {
    await deleteUser(pendingUserId)
    toast.success('User deleted.')
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    pendingUserId = null
  }
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold text-slate-900">Users</h1>
      <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="openCreate">
        New user
      </button>
    </div>

    <div class="mt-4">
      <input
        v-model="search"
        type="search"
        placeholder="Search users…"
        class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm"
        @keyup.enter="load"
        @blur="load"
      />
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Name</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Role</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Status</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Last login</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td class="px-4 py-3 text-slate-400" colspan="5">Loading…</td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td class="px-4 py-3 text-slate-400" colspan="5">No users found.</td>
          </tr>
          <tr v-for="user in users" :key="user.id">
            <td class="px-4 py-2">
              <p class="font-medium text-slate-900">{{ user.name }}</p>
              <p class="text-xs text-slate-400">{{ user.email }}</p>
            </td>
            <td class="px-4 py-2 text-slate-600">{{ user.role?.name }}</td>
            <td class="px-4 py-2">
              <Badge :tone="user.status === 'active' ? 'success' : 'neutral'">{{ user.status }}</Badge>
            </td>
            <td class="px-4 py-2 text-slate-500">{{ formatDate(user.last_login_at) }}</td>
            <td class="px-4 py-2 text-right space-x-3">
              <button class="text-xs text-indigo-600 hover:underline" @click="openEdit(user)">Edit</button>
              <button class="text-xs text-indigo-600 hover:underline" @click="handleForceReset(user)">Reset password</button>
              <button class="text-xs text-red-600 hover:underline" @click="confirmDelete(user)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <UserFormModal :open="formOpen" :user="editingUser" @close="formOpen = false" @submit="handleSubmit" />
    <GeneratedPasswordModal :open="passwordModalOpen" :password="generatedPassword" @close="passwordModalOpen = false" />
    <ConfirmDialog
      :open="confirmOpen"
      title="Delete user"
      :message="confirmMessage"
      confirm-label="Delete"
      danger
      @close="confirmOpen = false"
      @confirm="handleConfirmDelete"
    />
  </AppLayout>
</template>
