<script setup lang="ts">
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const route = useRoute()

const navItems = [
  { name: 'dashboard', label: 'Dashboard', icon: '📊' },
  { name: 'buckets.index', label: 'Buckets', icon: '🪣' },
  { name: 'tokens.index', label: 'Tokens', icon: '🔑' },
]

async function handleLogout(): Promise<void> {
  await auth.logout()
  toast.info('Logged out.')
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <div class="flex">
      <aside class="hidden md:flex md:w-60 md:flex-col md:fixed md:inset-y-0 border-r border-slate-200 bg-white">
        <div class="flex h-16 items-center px-5 border-b border-slate-200">
          <span class="text-lg font-semibold text-slate-900">FileServer</span>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1">
          <RouterLink
            v-for="item in navItems"
            :key="item.name"
            :to="{ name: item.name }"
            :class="[
              'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium',
              route.name === item.name || route.matched.some((r) => r.name === item.name)
                ? 'bg-indigo-50 text-indigo-700'
                : 'text-slate-600 hover:bg-slate-100',
            ]"
          >
            <span>{{ item.icon }}</span>
            {{ item.label }}
          </RouterLink>

          <RouterLink
            v-if="auth.canReadAudit"
            :to="{ name: 'audit-logs.index' }"
            :class="[
              'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium',
              route.name === 'audit-logs.index' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100',
            ]"
          >
            <span>🧾</span>
            Audit Logs
          </RouterLink>

          <RouterLink
            v-if="auth.canManageUsers"
            :to="{ name: 'users.index' }"
            :class="[
              'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium',
              route.name === 'users.index' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100',
            ]"
          >
            <span>👤</span>
            Users
          </RouterLink>
        </nav>
        <div class="border-t border-slate-200 p-4">
          <p class="text-sm font-medium text-slate-900 truncate">{{ auth.user?.name }}</p>
          <p class="text-xs text-slate-500 truncate">{{ auth.user?.role?.name }}</p>
          <RouterLink
            :to="{ name: 'change-password' }"
            class="mt-3 block w-full rounded-md border border-slate-200 px-3 py-1.5 text-center text-sm text-slate-600 hover:bg-slate-100"
          >
            Change password
          </RouterLink>
          <button
            class="mt-2 w-full rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100"
            @click="handleLogout"
          >
            Log out
          </button>
        </div>
      </aside>

      <div class="flex-1 md:ml-60">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6 md:hidden">
          <span class="text-lg font-semibold text-slate-900">FileServer</span>
          <button class="text-sm text-slate-600" @click="handleLogout">Log out</button>
        </header>
        <main class="p-6">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
