<script setup lang="ts">
import { humanBytes, formatDate } from '@/support/format'
import type { StoredObject } from '@/types/api'

defineProps<{
  open: boolean
  object: StoredObject | null
}>()

const emit = defineEmits<{ close: []; download: []; delete: [] }>()
</script>

<template>
  <Transition name="slide">
    <aside
      v-if="open && object"
      class="fixed inset-y-0 right-0 z-30 w-full max-w-md overflow-y-auto border-l border-slate-200 bg-white p-6 shadow-xl"
    >
      <div class="flex items-start justify-between">
        <h2 class="text-base font-semibold text-slate-900 break-all">{{ object.basename }}</h2>
        <button class="text-slate-400 hover:text-slate-600" @click="emit('close')">&times;</button>
      </div>

      <dl class="mt-6 space-y-3 text-sm">
        <div>
          <dt class="text-slate-500">Key</dt>
          <dd class="font-mono text-xs text-slate-800 break-all">{{ object.key }}</dd>
        </div>
        <div>
          <dt class="text-slate-500">Size</dt>
          <dd class="text-slate-800">{{ humanBytes(object.size_bytes) }}</dd>
        </div>
        <div>
          <dt class="text-slate-500">MIME type</dt>
          <dd class="text-slate-800">{{ object.mime_type ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-slate-500">Checksum (SHA-256)</dt>
          <dd class="font-mono text-xs text-slate-800 break-all">{{ object.checksum_sha256 }}</dd>
        </div>
        <div>
          <dt class="text-slate-500">Uploaded by</dt>
          <dd class="text-slate-800">{{ object.uploaded_by ?? object.uploaded_via_token ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-slate-500">Last modified</dt>
          <dd class="text-slate-800">{{ formatDate(object.updated_at) }}</dd>
        </div>
      </dl>

      <div class="mt-6 flex gap-2">
        <button
          class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
          @click="emit('download')"
        >
          Download
        </button>
        <button
          class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
          @click="emit('delete')"
        >
          Delete
        </button>
      </div>

      <div v-if="object.versions.length > 1" class="mt-8">
        <h3 class="text-sm font-semibold text-slate-900">Version history</h3>
        <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200">
          <li
            v-for="version in object.versions"
            :key="version.version_number"
            class="flex items-center justify-between px-3 py-2 text-xs"
          >
            <span class="text-slate-700">v{{ version.version_number }} · {{ humanBytes(version.size_bytes) }}</span>
            <span class="text-slate-400">{{ formatDate(version.created_at) }}</span>
          </li>
        </ul>
      </div>
    </aside>
  </Transition>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}
</style>
