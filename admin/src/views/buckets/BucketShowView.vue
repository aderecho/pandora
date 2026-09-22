<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Badge from '@/components/ui/Badge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import BucketFormModal from '@/components/buckets/BucketFormModal.vue'
import FileBrowser from '@/components/files/FileBrowser.vue'
import { deleteBucket, getBucket, updateBucket, type BucketUpdatePayload } from '@/api/buckets'
import { useToastStore } from '@/stores/toast'
import { errorMessage, humanBytes } from '@/support/format'
import type { Bucket, BucketUsage } from '@/types/api'

const props = defineProps<{ name: string }>()
const toast = useToastStore()
const router = useRouter()

const bucket = ref<Bucket | null>(null)
const usage = ref<BucketUsage | null>(null)
const loading = ref(true)
const tab = ref<'files' | 'settings'>('files')
const editOpen = ref(false)
const confirmOpen = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await getBucket(props.name)
    bucket.value = response.data
    usage.value = (response.meta as { usage: BucketUsage }).usage
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

async function handleUpdate(payload: BucketUpdatePayload): Promise<void> {
  try {
    bucket.value = await updateBucket(props.name, payload)
    toast.success('Bucket updated.')
    editOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

async function toggleArchive(): Promise<void> {
  if (!bucket.value) return
  try {
    bucket.value = await updateBucket(props.name, {
      status: bucket.value.status === 'active' ? 'archived' : 'active',
    })
    toast.success(bucket.value.status === 'archived' ? 'Bucket archived.' : 'Bucket reactivated.')
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

async function handleDelete(): Promise<void> {
  confirmOpen.value = false
  try {
    await deleteBucket(props.name)
    toast.success('Bucket deleted.')
    await router.push({ name: 'buckets.index' })
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div v-if="loading" class="text-sm text-slate-500">Loading…</div>
    <template v-else-if="bucket">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">{{ bucket.name }}</h1>
          <div class="mt-1 flex items-center gap-2">
            <Badge :tone="bucket.status === 'active' ? 'success' : 'neutral'">{{ bucket.status }}</Badge>
            <Badge v-if="bucket.versioning_enabled" tone="info">versioned</Badge>
            <Badge v-if="bucket.public_access" tone="warning">public</Badge>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100" @click="editOpen = true">
            Edit
          </button>
          <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100" @click="toggleArchive">
            {{ bucket.status === 'active' ? 'Archive' : 'Reactivate' }}
          </button>
          <button class="rounded-md border border-red-200 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50" @click="confirmOpen = true">
            Delete
          </button>
        </div>
      </div>

      <div v-if="usage" class="mt-4 grid grid-cols-3 gap-4 max-w-lg">
        <div class="rounded-lg border border-slate-200 bg-white p-3">
          <p class="text-xs text-slate-500">Files</p>
          <p class="text-lg font-semibold text-slate-900">{{ usage.file_count }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3">
          <p class="text-xs text-slate-500">Used</p>
          <p class="text-lg font-semibold text-slate-900">{{ humanBytes(usage.total_bytes) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3">
          <p class="text-xs text-slate-500">Quota</p>
          <p class="text-lg font-semibold text-slate-900">{{ usage.quota_bytes ? humanBytes(usage.quota_bytes) : 'Unlimited' }}</p>
        </div>
      </div>

      <div class="mt-6 border-b border-slate-200">
        <nav class="-mb-px flex gap-6">
          <button
            :class="['border-b-2 px-1 py-2 text-sm font-medium', tab === 'files' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']"
            @click="tab = 'files'"
          >
            Files
          </button>
          <button
            :class="['border-b-2 px-1 py-2 text-sm font-medium', tab === 'settings' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']"
            @click="tab = 'settings'"
          >
            Settings
          </button>
        </nav>
      </div>

      <div class="mt-4">
        <FileBrowser v-if="tab === 'files'" :bucket="bucket.name" />
        <dl v-else class="grid grid-cols-2 gap-4 text-sm max-w-lg">
          <div><dt class="text-slate-500">Storage path</dt><dd class="text-slate-800">{{ bucket.storage_path }}</dd></div>
          <div><dt class="text-slate-500">Driver</dt><dd class="text-slate-800">{{ bucket.driver }}</dd></div>
          <div><dt class="text-slate-500">Max file size</dt><dd class="text-slate-800">{{ bucket.max_file_size_bytes ? humanBytes(bucket.max_file_size_bytes) : 'Unlimited' }}</dd></div>
          <div><dt class="text-slate-500">Allowed extensions</dt><dd class="text-slate-800">{{ bucket.allowed_extensions?.join(', ') || 'Any' }}</dd></div>
          <div><dt class="text-slate-500">Created by</dt><dd class="text-slate-800">{{ bucket.created_by ?? '—' }}</dd></div>
        </dl>
      </div>

      <BucketFormModal :open="editOpen" :bucket="bucket" @close="editOpen = false" @submit="handleUpdate" />
      <ConfirmDialog
        :open="confirmOpen"
        title="Delete bucket"
        :message="`Delete bucket ${bucket.name}? Objects are retained per the platform's retention policy.`"
        confirm-label="Delete"
        danger
        @close="confirmOpen = false"
        @confirm="handleDelete"
      />
    </template>
  </AppLayout>
</template>
