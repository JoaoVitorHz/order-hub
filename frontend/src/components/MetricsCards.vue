<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useOrdersStore } from '@/stores/ordersStore'

const store = useOrdersStore()

const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

const cards = [
  { key: 'total_orders',     label: 'Total de Pedidos', icon: '📦', fmt: (v) => v },
  { key: 'approved_orders',  label: 'Aprovados',        icon: '✅', fmt: (v) => v },
  { key: 'pending_orders',   label: 'Pendentes',        icon: '⏳', fmt: (v) => v },
  { key: 'cancelled_orders', label: 'Cancelados',       icon: '❌', fmt: (v) => v },
  { key: 'total_revenue',    label: 'Receita Total',    icon: '💰', fmt: (v) => fmt.format(v) },
  { key: 'avg_ticket',       label: 'Ticket Médio',     icon: '🎯', fmt: (v) => fmt.format(v) },
]

let interval = null

onMounted(async () => {
  await store.loadMetrics()
  interval = setInterval(() => store.loadMetrics(), 60_000)
})

onUnmounted(() => clearInterval(interval))
</script>

<template>
  <div>
    <div class="mb-3 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-800">Métricas</h2>
      <span v-if="store.minutesSinceRefresh !== null" class="text-xs text-gray-500">
        atualizado há {{ store.minutesSinceRefresh }}min
        <span v-if="store.metricsLoading" class="ml-1 inline-block h-2 w-2 animate-spin rounded-full border border-gray-400 border-t-transparent"></span>
      </span>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
      <template v-if="store.metricsLoading && !store.metrics">
        <div v-for="i in 6" :key="i" class="card p-4">
          <div class="skeleton mb-2 h-4 w-3/4"></div>
          <div class="skeleton h-7 w-1/2"></div>
        </div>
      </template>

      <template v-else>
        <div v-for="card in cards" :key="card.key" class="card p-4">
          <p class="mb-1 flex items-center gap-1 text-xs font-medium text-gray-500">
            <span aria-hidden="true">{{ card.icon }}</span>
            {{ card.label }}
          </p>
          <p class="text-xl font-bold text-gray-900">
            {{ store.metrics ? card.fmt(store.metrics[card.key]) : '—' }}
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
