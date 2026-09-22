<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import { listRoles } from '@/api/users'
import type { Role, User } from '@/types/api'

const props = defineProps<{ open: boolean; user?: User | null }>()
const emit = defineEmits<{
  close: []
  submit: [payload: { name: string; email: string; role_id: number; status?: 'active' | 'disabled' }]
}>()

const roles = ref<Role[]>([])
const name = ref('')
const email = ref('')
const roleId = ref<number | null>(null)
const status = ref<'active' | 'disabled'>('active')

onMounted(async () => {
  roles.value = await listRoles()
})

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    name.value = props.user?.name ?? ''
    email.value = props.user?.email ?? ''
    roleId.value = props.user?.role?.id ?? roles.value[0]?.id ?? null
    status.value = props.user?.status ?? 'active'
  },
)

function handleSubmit(): void {
  if (roleId.value === null) return
  emit('submit', { name: name.value.trim(), email: email.value.trim(), role_id: roleId.value, status: status.value })
}
</script>

<template>
  <Modal :open="open" :title="user ? `Edit ${user.name}` : 'Create user'" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div>
        <label class="block text-sm font-medium text-slate-700">Name</label>
        <input v-model="name" type="text" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Email</label>
        <input v-model="email" type="email" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Role</label>
        <select v-model.number="roleId" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
          <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
      </div>
      <div v-if="user">
        <label class="block text-sm font-medium text-slate-700">Status</label>
        <select v-model="status" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
          <option value="active">Active</option>
          <option value="disabled">Disabled</option>
        </select>
      </div>
      <p v-if="!user" class="text-xs text-slate-400">
        A one-time password will be generated and shown after creation (the user must change it on first login).
      </p>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100" @click="emit('close')">
          Cancel
        </button>
        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
          {{ user ? 'Save changes' : 'Create user' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
