<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import ObjectDrawer from './ObjectDrawer.vue'
import {
  deleteObject,
  downloadObject,
  getObjectMetadata,
  listObjects,
  makeDirectory,
  triggerBrowserDownload,
  uploadObject,
} from '@/api/objects'
import { useToastStore } from '@/stores/toast'
import { errorMessage, humanBytes } from '@/support/format'
import type { FolderEntry, ListingEntry, StoredObject } from '@/types/api'

const props = defineProps<{ bucket: string }>()

const toast = useToastStore()
const prefix = ref('')
const entries = ref<ListingEntry[]>([])
const loading = ref(true)
const searchTerm = ref('')
const uploadProgress = ref<number | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const drawerObject = ref<StoredObject | null>(null)
const drawerOpen = ref(false)

const confirmOpen = ref(false)
const confirmMessage = ref('')
let confirmAction: (() => Promise<void>) | null = null

const breadcrumbs = computed(() => {
  const parts = prefix.value.split('/').filter(Boolean)
  const crumbs: { label: string; prefix: string }[] = [{ label: props.bucket, prefix: '' }]
  let acc = ''
  for (const part of parts) {
    acc += `${part}/`
    crumbs.push({ label: part, prefix: acc })
  }
  return crumbs
})

function isFolder(entry: ListingEntry): entry is FolderEntry {
  return entry.type === 'folder'
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await listObjects(props.bucket, {
      prefix: searchTerm.value ? '' : prefix.value,
      name: searchTerm.value || undefined,
      per_page: 200,
    })
    entries.value = response.data
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

watch(() => props.bucket, () => {
  prefix.value = ''
  searchTerm.value = ''
  load()
}, { immediate: true })

function openFolder(folder: FolderEntry): void {
  prefix.value = folder.key
  searchTerm.value = ''
  load()
}

function goToBreadcrumb(target: string): void {
  prefix.value = target
  searchTerm.value = ''
  load()
}

async function openMetadata(object: StoredObject): Promise<void> {
  try {
    drawerObject.value = await getObjectMetadata(props.bucket, object.key)
    drawerOpen.value = true
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

async function handleDownload(object: StoredObject): Promise<void> {
  try {
    const blob = await downloadObject(props.bucket, object.key)
    triggerBrowserDownload(blob, object.basename)
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

function confirmDelete(object: StoredObject): void {
  confirmMessage.value = `Delete ${object.key}? This cannot be undone.`
  confirmAction = async () => {
    try {
      await deleteObject(props.bucket, object.key)
      toast.success('Object deleted.')
      drawerOpen.value = false
      await load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }
  confirmOpen.value = true
}

async function handleConfirm(): Promise<void> {
  confirmOpen.value = false
  if (confirmAction) await confirmAction()
  confirmAction = null
}

function triggerUpload(): void {
  fileInput.value?.click()
}

async function handleFileSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploadProgress.value = 0
  try {
    await uploadObject(props.bucket, `${prefix.value}${file.name}`, file, (pct) => {
      uploadProgress.value = pct
    })
    toast.success(`Uploaded ${file.name}.`)
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    uploadProgress.value = null
    input.value = ''
  }
}

async function handleMkdir(): Promise<void> {
  const name = window.prompt('Folder name')
  if (!name) return
  try {
    await makeDirectory(props.bucket, `${prefix.value}${name}`)
    toast.success('Folder created.')
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <nav class="flex items-center gap-1 text-sm text-slate-500">
        <template v-for="(crumb, index) in breadcrumbs" :key="crumb.prefix">
          <button class="hover:text-indigo-600" @click="goToBreadcrumb(crumb.prefix)">{{ crumb.label }}</button>
          <span v-if="index < breadcrumbs.length - 1" class="text-slate-300">/</span>
        </template>
      </nav>

      <div class="flex items-center gap-2">
        <input
          v-model="searchTerm"
          type="search"
          placeholder="Search this bucket…"
          class="rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          @keyup.enter="load"
        />
        <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100" @click="handleMkdir">
          New folder
        </button>
        <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="triggerUpload">
          Upload
        </button>
        <input ref="fileInput" type="file" class="hidden" @change="handleFileSelected" />
      </div>
    </div>

    <p v-if="uploadProgress !== null" class="mt-3 text-sm text-slate-600">Uploading… {{ uploadProgress }}%</p>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Name</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Size</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Type</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td class="px-4 py-3 text-slate-400" colspan="4">Loading…</td>
          </tr>
          <tr v-else-if="entries.length === 0">
            <td class="px-4 py-3 text-slate-400" colspan="4">This folder is empty.</td>
          </tr>
          <template v-else>
            <tr
              v-for="entry in entries"
              :key="entry.key"
              class="cursor-pointer hover:bg-slate-50"
              @click="isFolder(entry) ? openFolder(entry) : openMetadata(entry)"
            >
              <td class="px-4 py-2 text-slate-800">
                <span v-if="isFolder(entry)">📁 {{ entry.key.replace(prefix, '') }}</span>
                <span v-else>📄 {{ entry.basename }}</span>
              </td>
              <td class="px-4 py-2 text-slate-500">{{ isFolder(entry) ? '—' : humanBytes(entry.size_bytes) }}</td>
              <td class="px-4 py-2 text-slate-500">
                <Badge v-if="isFolder(entry)" tone="neutral">folder</Badge>
                <Badge v-else tone="info">{{ entry.mime_type ?? 'file' }}</Badge>
              </td>
              <td class="px-4 py-2 text-right">
                <button
                  v-if="!isFolder(entry)"
                  class="text-xs text-red-600 hover:underline"
                  @click.stop="confirmDelete(entry)"
                >
                  Delete
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <ObjectDrawer
      :open="drawerOpen"
      :object="drawerObject"
      @close="drawerOpen = false"
      @download="drawerObject && handleDownload(drawerObject)"
      @delete="drawerObject && confirmDelete(drawerObject)"
    />

    <ConfirmDialog
      :open="confirmOpen"
      title="Delete object"
      :message="confirmMessage"
      confirm-label="Delete"
      danger
      @close="confirmOpen = false"
      @confirm="handleConfirm"
    />
  </div>
</template>
