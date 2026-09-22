<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  sublabel?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-5">
    <p class="text-sm font-medium text-slate-500">{{ label }}</p>
    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ value }}</p>
    <p v-if="sublabel" class="mt-1 text-xs text-slate-400">{{ sublabel }}</p>
  </div>
</template>
