<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Badge from '@/components/ui/Badge.vue'
import BucketFormModal from '@/components/buckets/BucketFormModal.vue'
import { createBucket, listBuckets } from '@/api/buckets'
import { useToastStore } from '@/stores/toast'
import { errorMessage } from '@/support/format'
import type { Bucket } from '@/types/api'
import type { BucketCreatePayload } from '@/api/buckets'

const toast = useToastStore()
const buckets = ref<Bucket[]>([])
const loading = ref(true)
const search = ref('')
const formOpen = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await listBuckets({ search: search.value || undefined })
    buckets.value = response.data
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

async function handleCreate(payload: BucketCreatePayload): Promise<void> {
  try {
    await createBucket(payload)
    toast.success('Bucket created.')
    formOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold text-slate-900">Buckets</h1>
      <button
        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
        @click="formOpen = true"
      >
        New bucket
      </button>
    </div>

    <div class="mt-4">
      <input
        v-model="search"
        type="search"
        placeholder="Search buckets…"
        class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        @keyup.enter="load"
        @blur="load"
      />
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Name</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Status</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Versioning</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Public</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td class="px-4 py-3 text-slate-400" colspan="4">Loading…</td>
          </tr>
          <tr v-else-if="buckets.length === 0">
            <td class="px-4 py-3 text-slate-400" colspan="4">No buckets found.</td>
          </tr>
          <tr
            v-for="bucket in buckets"
            :key="bucket.name"
            class="cursor-pointer hover:bg-slate-50"
            @click="$router.push({ name: 'buckets.show', params: { name: bucket.name } })"
          >
            <td class="px-4 py-2 font-medium text-slate-900">{{ bucket.name }}</td>
            <td class="px-4 py-2">
              <Badge :tone="bucket.status === 'active' ? 'success' : 'neutral'">{{ bucket.status }}</Badge>
            </td>
            <td class="px-4 py-2 text-slate-600">{{ bucket.versioning_enabled ? 'Enabled' : 'Disabled' }}</td>
            <td class="px-4 py-2 text-slate-600">{{ bucket.public_access ? 'Yes' : 'No' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <BucketFormModal :open="formOpen" @close="formOpen = false" @submit="handleCreate" />
  </AppLayout>
</template>
