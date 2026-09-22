<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Badge from '@/components/ui/Badge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import TokenCreateModal from '@/components/tokens/TokenCreateModal.vue'
import TokenRawValueModal from '@/components/tokens/TokenRawValueModal.vue'
import { createToken, listTokens, revokeToken, rotateToken } from '@/api/tokens'
import { useToastStore } from '@/stores/toast'
import { errorMessage, formatDate } from '@/support/format'
import type { ApiToken } from '@/types/api'
import type { TokenCreatePayload } from '@/api/tokens'

const toast = useToastStore()
const tokens = ref<ApiToken[]>([])
const loading = ref(true)
const createOpen = ref(false)
const rawValueOpen = ref(false)
const rawToken = ref<string | null>(null)

const confirmOpen = ref(false)
const confirmMessage = ref('')
let pendingTokenId: number | null = null

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await listTokens()
    tokens.value = response.data
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

async function handleCreate(payload: TokenCreatePayload): Promise<void> {
  try {
    const result = await createToken(payload)
    createOpen.value = false
    rawToken.value = result.raw_token
    rawValueOpen.value = true
    toast.success('Token created.')
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

async function handleRotate(token: ApiToken): Promise<void> {
  try {
    const result = await rotateToken(token.id)
    rawToken.value = result.raw_token
    rawValueOpen.value = true
    toast.success('Token rotated.')
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  }
}

function confirmRevoke(token: ApiToken): void {
  confirmMessage.value = `Revoke "${token.name}"? Any client using it will immediately lose access.`
  pendingTokenId = token.id
  confirmOpen.value = true
}

async function handleConfirmRevoke(): Promise<void> {
  confirmOpen.value = false
  if (pendingTokenId === null) return
  try {
    await revokeToken(pendingTokenId)
    toast.success('Token revoked.')
    await load()
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    pendingTokenId = null
  }
}

onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold text-slate-900">API Tokens</h1>
      <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="createOpen = true">
        New token
      </button>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Name</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Owner</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Status</th>
            <th class="px-4 py-2 text-left font-medium text-slate-500">Last used</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td class="px-4 py-3 text-slate-400" colspan="5">Loading…</td>
          </tr>
          <tr v-else-if="tokens.length === 0">
            <td class="px-4 py-3 text-slate-400" colspan="5">No tokens yet.</td>
          </tr>
          <tr v-for="token in tokens" :key="token.id">
            <td class="px-4 py-2">
              <p class="font-medium text-slate-900">{{ token.name }}</p>
              <p class="font-mono text-xs text-slate-400">{{ token.token_prefix }}</p>
            </td>
            <td class="px-4 py-2 text-slate-600">{{ token.owner }}</td>
            <td class="px-4 py-2">
              <Badge :tone="token.status === 'active' ? 'success' : 'danger'">{{ token.status }}</Badge>
            </td>
            <td class="px-4 py-2 text-slate-500">{{ formatDate(token.last_used_at) }}</td>
            <td class="px-4 py-2 text-right space-x-3">
              <button class="text-xs text-indigo-600 hover:underline" @click="handleRotate(token)">Rotate</button>
              <button
                v-if="token.status === 'active'"
                class="text-xs text-red-600 hover:underline"
                @click="confirmRevoke(token)"
              >
                Revoke
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <TokenCreateModal :open="createOpen" @close="createOpen = false" @submit="handleCreate" />
    <TokenRawValueModal :open="rawValueOpen" :raw-token="rawToken" @close="rawValueOpen = false" />
    <ConfirmDialog
      :open="confirmOpen"
      title="Revoke token"
      :message="confirmMessage"
      confirm-label="Revoke"
      danger
      @close="confirmOpen = false"
      @confirm="handleConfirmRevoke"
    />
  </AppLayout>
</template>
