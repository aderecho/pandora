<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import StatTile from '@/components/ui/StatTile.vue'
import { getDashboardMetrics } from '@/api/dashboard'
import { humanBytes, formatDate, errorMessage } from '@/support/format'
import type { DashboardMetrics } from '@/types/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const metrics = ref<DashboardMetrics | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  if (!auth.canReadAudit) {
    loading.value = false
    return
  }
  try {
    metrics.value = await getDashboardMetrics()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout>
    <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
    <p class="mt-1 text-sm text-slate-500">Welcome back, {{ auth.user?.name }}.</p>

    <div v-if="loading" class="mt-6 text-sm text-slate-500">Loading metrics…</div>
    <p v-else-if="error" class="mt-6 text-sm text-red-600">{{ error }}</p>
    <div v-else-if="!auth.canReadAudit" class="mt-6 text-sm text-slate-500">
      Ask a Storage Admin for a bucket grant or check <RouterLink class="text-indigo-600" :to="{ name: 'buckets.index' }">Buckets</RouterLink> to get started.
    </div>
    <template v-else-if="metrics">
      <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <StatTile label="Buckets" :value="metrics.buckets.total" />
        <StatTile label="Storage used" :value="humanBytes(metrics.storage.total_bytes)" :sublabel="`${metrics.storage.file_count} files`" />
        <StatTile label="Active users" :value="metrics.users.active" :sublabel="`${metrics.users.disabled} disabled`" />
        <StatTile label="Active tokens" :value="metrics.tokens.active" :sublabel="`${metrics.tokens.revoked} revoked`" />
        <StatTile label="Failed requests (24h)" :value="metrics.activity.failed_requests_last_24h" />
      </div>

      <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <StatTile label="Uploads (24h)" :value="metrics.activity.uploads_last_24h" />
        <StatTile label="Downloads (24h)" :value="metrics.activity.downloads_last_24h" />
      </div>

      <div class="mt-8">
        <h2 class="text-sm font-semibold text-slate-900">Recent uploads</h2>
        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left font-medium text-slate-500">Actor</th>
                <th class="px-4 py-2 text-left font-medium text-slate-500">Key</th>
                <th class="px-4 py-2 text-left font-medium text-slate-500">When</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="metrics.recent_uploads.length === 0">
                <td class="px-4 py-3 text-slate-400" colspan="3">No uploads yet.</td>
              </tr>
              <tr v-for="row in metrics.recent_uploads" :key="row.id">
                <td class="px-4 py-2 text-slate-700">{{ row.actor_type }} #{{ row.actor_id ?? '—' }}</td>
                <td class="px-4 py-2 text-slate-700 font-mono text-xs">{{ row.object_key }}</td>
                <td class="px-4 py-2 text-slate-500">{{ formatDate(row.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </AppLayout>
</template>
