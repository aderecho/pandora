<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import { listBuckets } from '@/api/buckets'
import { PERMISSION_KEYS } from '@/types/api'
import type { Bucket } from '@/types/api'
import type { TokenBucketScopeInput, TokenCreatePayload } from '@/api/tokens'

defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; submit: [payload: TokenCreatePayload] }>()

const buckets = ref<Bucket[]>([])
const name = ref('')
const expiresAt = ref('')
const scopes = reactive<TokenBucketScopeInput[]>([])

onMounted(async () => {
  const response = await listBuckets()
  buckets.value = response.data
})

function addScope(): void {
  scopes.push({ bucket: buckets.value[0]?.name ?? '', path_prefix: '', permissions: ['object:read'] })
}

function removeScope(index: number): void {
  scopes.splice(index, 1)
}

function handleSubmit(): void {
  const abilities = Array.from(new Set(scopes.flatMap((s) => s.permissions)))

  emit('submit', {
    name: name.value.trim(),
    abilities: abilities.length ? abilities : ['object:read'],
    expires_at: expiresAt.value || null,
    bucket_scopes: scopes.map((s) => ({
      bucket: s.bucket,
      path_prefix: s.path_prefix || null,
      permissions: s.permissions,
    })),
  })

  name.value = ''
  expiresAt.value = ''
  scopes.splice(0, scopes.length)
}
</script>

<template>
  <Modal :open="open" title="Create API token" max-width="max-w-2xl" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-slate-700">Name</label>
          <input
            v-model="name"
            type="text"
            required
            placeholder="CI deploy token"
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Expires (optional)</label>
          <input
            v-model="expiresAt"
            type="date"
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between">
          <label class="block text-sm font-medium text-slate-700">Bucket scopes</label>
          <button type="button" class="text-xs text-indigo-600 hover:underline" @click="addScope">+ Add scope</button>
        </div>

        <div v-if="scopes.length === 0" class="mt-2 text-xs text-slate-400">
          No scopes yet — add at least one bucket scope so this token can do anything.
        </div>

        <div v-for="(scope, index) in scopes" :key="index" class="mt-3 rounded-lg border border-slate-200 p-3">
          <div class="flex items-center gap-2">
            <select v-model="scope.bucket" class="flex-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option v-for="b in buckets" :key="b.name" :value="b.name">{{ b.name }}</option>
            </select>
            <input
              v-model="scope.path_prefix"
              type="text"
              placeholder="path/prefix/ (optional)"
              class="flex-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
            <button type="button" class="text-xs text-red-600 hover:underline" @click="removeScope(index)">Remove</button>
          </div>
          <div class="mt-2 flex flex-wrap gap-3">
            <label v-for="perm in PERMISSION_KEYS" :key="perm" class="flex items-center gap-1 text-xs text-slate-600">
              <input v-model="scope.permissions" type="checkbox" :value="perm" class="rounded border-slate-300" />
              {{ perm }}
            </label>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100" @click="emit('close')">
          Cancel
        </button>
        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
          Create token
        </button>
      </div>
    </form>
  </Modal>
</template>
