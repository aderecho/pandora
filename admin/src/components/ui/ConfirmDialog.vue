<script setup lang="ts">
import Modal from './Modal.vue'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    message: string
    confirmLabel?: string
    danger?: boolean
  }>(),
  { confirmLabel: 'Confirm', danger: false },
)

const emit = defineEmits<{ close: []; confirm: [] }>()
</script>

<template>
  <Modal :open="open" :title="title" max-width="max-w-sm" @close="emit('close')">
    <p class="text-sm text-slate-600">{{ message }}</p>
    <div class="mt-6 flex justify-end gap-2">
      <button
        class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
        @click="emit('close')"
      >
        Cancel
      </button>
      <button
        :class="[
          'rounded-md px-3 py-1.5 text-sm font-medium text-white',
          danger ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700',
        ]"
        @click="emit('confirm')"
      >
        {{ confirmLabel }}
      </button>
    </div>
  </Modal>
</template>
