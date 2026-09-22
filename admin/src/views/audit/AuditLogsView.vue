<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Badge from '@/components/ui/Badge.vue'
import { auditLogExportUrl, listAuditLogs, type AuditLogFilters } from '@/api/auditLogs'
import { useToastStore } from '@/stores/toast'
import { errorMessage, formatDate } from '@/support/format'
import type { AuditLog, Pagination } from '@/types/api'

const toast = useToastStore()
const logs = ref<AuditLog[]>([])
const pagination = ref<Pagination | null>(null)
const loading = ref(true)

const filters = reactive<AuditLogFilters>({
  actor_type: '',
  status: '',
  action: '',
  page: 1,
  per_page: 25,
})

const exportUrl = computed(() => auditLogExportUrl(filters))

async function load(): Promise<void> {
  loading.value = true
  try {
    const cleaned = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''))
    const response = await listAuditLogs(cleaned)
    logs.value = response.data
    pagination.value = response.meta?.pagination ?? null
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

function goToPage(page: number): void {
  filters.page = page
  load()
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold text-slate-900">Audit Logs</h1>
      <a
        :href="exportUrl"
        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100"
      >
        Export CSV
      </a>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
      <select v-model="filters.actor_type" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm" @change="load">
        <option value="">All actors</option>
        <option value="user">User</option>
        <option value="token">Token</option>
        <option value="system">System</option>
      </select>
      <select v-model="filters.status" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm" @change="load">
        <option value="">All statuses</option>
        <option value="success">Success</option>
        <option value="failure">Failure</option>
      </select>
      <input
        v-model="filters.action"
        type="text"
        placeholder="Filter by action…"
        class="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
        @keyup.enter="load"
      />
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2 text-left font-medium text-slate-500">When</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Actor</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Action</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Bucket / Key</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">IP</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td class="px-4 py-3 text-slate-400" colspan="6">Loading…</td>
          </tr>
          <tr v-else-if="logs.length === 0">
            <td class="px-4 py-3 text-slate-400" colspan="6">No matching audit log entries.</td>
          </tr>
          <tr v-for="log in logs" :key="log.id">
            <td class="px-4 py-2 text-slate-500">{{ formatDate(log.created_at) }}</td>
            <td class="px-4 py-2 text-slate-700">{{ log.actor }}</td>
            <td class="px-4 py-2 font-mono text-xs text-slate-700">{{ log.action }}</td>
            <td class="px-4 py-2 text-slate-500">
              <span v-if="log.bucket">{{ log.bucket }}</span>
              <span v-if="log.object_key" class="font-mono text-xs"> / {{ log.object_key }}</span>
              <span v-if="!log.bucket && !log.object_key">—</span>
            </td>
            <td class="px-4 py-2 text-slate-500">{{ log.ip_address }}</td>
            <td class="px-4 py-2">
              <Badge :tone="log.status === 'success' ? 'success' : 'danger'">{{ log.status }}</Badge>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination && pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-slate-500">
      <span>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total)</span>
      <div class="flex gap-2">
        <button
          class="rounded-md border border-slate-300 px-2 py-1 disabled:opacity-40"
          :disabled="pagination.current_page <= 1"
          @click="goToPage(pagination.current_page - 1)"
        >
          Previous
        </button>
        <button
          class="rounded-md border border-slate-300 px-2 py-1 disabled:opacity-40"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="goToPage(pagination.current_page + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </AppLayout>
</template>
