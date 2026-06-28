<script setup>
import { ref } from 'vue'
import MetricsCards from '@/components/MetricsCards.vue'
import OrdersTable from '@/components/OrdersTable.vue'
import OrderDrawer from '@/components/OrderDrawer.vue'

const drawerRef = ref(null)

function openOrder(id) {
  drawerRef.value?.open(id)
}
</script>

<template>
  <div class="space-y-6">
    <MetricsCards />

    <div>
      <h2 class="mb-3 text-lg font-semibold text-gray-800">Pedidos</h2>
      <OrdersTable @openOrder="openOrder" />
    </div>

    <OrderDrawer ref="drawerRef" />
  </div>
</template>
