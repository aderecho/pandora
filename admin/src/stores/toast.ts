import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: number
  type: 'success' | 'error' | 'info'
  message: string
}

let nextId = 1

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  function push(type: Toast['type'], message: string, timeoutMs = 4000): void {
    const id = nextId++
    toasts.value.push({ id, type, message })
    setTimeout(() => dismiss(id), timeoutMs)
  }

  function dismiss(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const success = (message: string) => push('success', message)
  const error = (message: string) => push('error', message)
  const info = (message: string) => push('info', message)

  return { toasts, push, dismiss, success, error, info }
})
