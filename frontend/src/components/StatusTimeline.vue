<script setup>
const props = defineProps({
  logs: { type: Array, default: () => [] },
})

const statusColor = {
  pending:   'bg-yellow-400',
  approved:  'bg-green-500',
  cancelled: 'bg-red-500',
  refunded:  'bg-purple-500',
}

const fmtDate = (iso) =>
  new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))
</script>

<template>
  <ol class="relative ml-3 border-l border-gray-200" aria-label="Histórico de status">
    <li v-for="(log, i) in logs" :key="log.id" class="mb-6 ml-6">
      <span
        :class="['absolute -left-2 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-white', statusColor[log.to_status] ?? 'bg-gray-400']"
        aria-hidden="true"
      ></span>
      <p class="text-sm font-semibold capitalize text-gray-800">{{ log.to_status }}</p>
      <p v-if="log.from_status" class="text-xs text-gray-500">de <span class="capitalize">{{ log.from_status }}</span></p>
      <time class="text-xs text-gray-400">{{ fmtDate(log.changed_at) }}</time>
      <p v-if="log.changed_by" class="text-xs text-gray-500">por {{ log.changed_by }}</p>
      <p v-if="log.notes" class="mt-0.5 rounded bg-gray-50 px-2 py-1 text-xs text-gray-600">{{ log.notes }}</p>
    </li>
  </ol>
</template>
