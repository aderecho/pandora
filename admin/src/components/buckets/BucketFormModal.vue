<script setup lang="ts">
import { ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import type { Bucket } from '@/types/api'

const props = defineProps<{
  open: boolean
  bucket?: Bucket | null
}>()

const emit = defineEmits<{
  close: []
  submit: [
    payload: {
      name: string
      quota_bytes: number | null
      max_file_size_bytes: number | null
      allowed_extensions: string[] | null
      versioning_enabled: boolean
      public_access: boolean
    },
  ]
}>()

const name = ref('')
const quotaGb = ref<number | null>(null)
const maxFileMb = ref<number | null>(null)
const allowedExtensions = ref('')
const versioningEnabled = ref(false)
const publicAccess = ref(false)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    name.value = props.bucket?.name ?? ''
    quotaGb.value = props.bucket?.quota_bytes ? Math.round(props.bucket.quota_bytes / 1024 / 1024 / 1024) : null
    maxFileMb.value = props.bucket?.max_file_size_bytes
      ? Math.round(props.bucket.max_file_size_bytes / 1024 / 1024)
      : null
    allowedExtensions.value = props.bucket?.allowed_extensions?.join(', ') ?? ''
    versioningEnabled.value = props.bucket?.versioning_enabled ?? false
    publicAccess.value = props.bucket?.public_access ?? false
  },
)

function handleSubmit(): void {
  emit('submit', {
    name: name.value.trim(),
    quota_bytes: quotaGb.value ? quotaGb.value * 1024 * 1024 * 1024 : null,
    max_file_size_bytes: maxFileMb.value ? maxFileMb.value * 1024 * 1024 : null,
    allowed_extensions: allowedExtensions.value.trim()
      ? allowedExtensions.value.split(',').map((s) => s.trim()).filter(Boolean)
      : null,
    versioning_enabled: versioningEnabled.value,
    public_access: publicAccess.value,
  })
}
</script>

<template>
  <Modal :open="open" :title="bucket ? `Edit ${bucket.name}` : 'Create bucket'" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div>
        <label class="block text-sm font-medium text-slate-700">Name</label>
        <input
          v-model="name"
          type="text"
          :disabled="!!bucket"
          required
          pattern="[a-z0-9][a-z0-9\-]*[a-z0-9]"
          placeholder="documents"
          class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-50 disabled:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        />
        <p class="mt-1 text-xs text-slate-400">Lowercase letters, numbers, hyphens.</p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-slate-700">Quota (GB)</label>
          <input
            v-model.number="quotaGb"
            type="number"
            min="0"
            placeholder="No limit"
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Max file size (MB)</label>
          <input
            v-model.number="maxFileMb"
            type="number"
            min="0"
            placeholder="No limit"
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700">Allowed extensions</label>
        <input
          v-model="allowedExtensions"
          type="text"
          placeholder="pdf, png, docx (blank = any)"
          class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        />
      </div>

      <div class="flex gap-6">
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="versioningEnabled" type="checkbox" class="rounded border-slate-300" />
          Versioning
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="publicAccess" type="checkbox" class="rounded border-slate-300" />
          Public access
        </label>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
          {{ bucket ? 'Save changes' : 'Create bucket' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
