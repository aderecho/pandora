<script setup lang="ts">
import { ref } from 'vue'
import Modal from '@/components/ui/Modal.vue'

const props = defineProps<{ open: boolean; password: string | null }>()
const emit = defineEmits<{ close: [] }>()

const copied = ref(false)

async function copy(): Promise<void> {
  if (!props.password) return
  await navigator.clipboard.writeText(props.password)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}
</script>

<template>
  <Modal :open="open" title="Generated password" @close="emit('close')">
    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
      This password is shown only once. Share it with the user securely — they'll be required to
      change it on first login.
    </p>
    <div class="mt-4 flex items-center gap-2">
      <code class="flex-1 break-all rounded-md bg-slate-100 px-3 py-2 text-sm">{{ password }}</code>
      <button class="rounded-md border border-slate-300 px-3 py-2 text-xs text-slate-700 hover:bg-slate-100" @click="copy">
        {{ copied ? 'Copied!' : 'Copy' }}
      </button>
    </div>
    <div class="mt-6 flex justify-end">
      <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="emit('close')">
        Done
      </button>
    </div>
  </Modal>
</template>
