import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { fetchOrders, fetchMetrics, updateOrderStatus } from '@/api/orders'

export const useOrdersStore = defineStore('orders', () => {
  const orders = ref([])
  const meta = ref({})
  const metrics = ref(null)
  const metricsLoadedAt = ref(null)
  const loading = ref(false)
  const metricsLoading = ref(false)
  const selectedIds = ref(new Set())
  const activeOrder = ref(null)

  const selectedCount = computed(() => selectedIds.value.size)
  const minutesSinceRefresh = computed(() => {
    if (!metricsLoadedAt.value) return null
    return Math.floor((Date.now() - metricsLoadedAt.value) / 60000)
  })

  async function loadOrders(params) {
    loading.value = true
    try {
      const { data } = await fetchOrders(params)
      orders.value = data.data
      meta.value = data.meta
    } finally {
      loading.value = false
    }
  }

  async function loadMetrics() {
    metricsLoading.value = true
    try {
      const { data } = await fetchMetrics()
      metrics.value = data.data
      metricsLoadedAt.value = Date.now()
    } finally {
      metricsLoading.value = false
    }
  }

  async function changeStatus(orderId, status, notes) {
    const { data } = await updateOrderStatus(orderId, status, notes)
    const updated = data.data
    const idx = orders.value.findIndex((o) => o.id === orderId)
    if (idx !== -1) orders.value[idx] = updated
    if (activeOrder.value?.id === orderId) activeOrder.value = updated
    // Refresh metrics since cache was invalidated
    await loadMetrics()
    return updated
  }

  function toggleSelect(id) {
    if (selectedIds.value.has(id)) {
      selectedIds.value.delete(id)
    } else {
      selectedIds.value.add(id)
    }
  }

  function clearSelection() {
    selectedIds.value.clear()
  }

  function selectAll() {
    orders.value.forEach((o) => selectedIds.value.add(o.id))
  }

  return {
    orders, meta, metrics, metricsLoadedAt, loading, metricsLoading,
    selectedIds, selectedCount, activeOrder, minutesSinceRefresh,
    loadOrders, loadMetrics, changeStatus,
    toggleSelect, clearSelection, selectAll,
  }
})
