<script setup>
import { ref, computed } from 'vue'
import { fetchOrder } from '@/api/orders'
import { useOrdersStore } from '@/stores/ordersStore'
import StatusTimeline from './StatusTimeline.vue'

const store = useOrdersStore()
const order = ref(null)
const loading = ref(false)
const error = ref(null)
const statusError = ref(null)
const updating = ref(false)
const selectedStatus = ref('')

const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDate = (iso) =>
  new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))

const validTransitions = computed(() => order.value?.valid_transitions ?? [])

async function open(id) {
  loading.value = true
  error.value = null
  order.value = null
  selectedStatus.value = ''
  const { data } = await fetchOrder(id)
  order.value = data.data
  loading.value = false
}

async function submitStatus() {
  if (!selectedStatus.value) return
  statusError.value = null
  updating.value = true
  try {
    order.value = await store.changeStatus(order.value.id, selectedStatus.value)
    selectedStatus.value = ''
  } catch (err) {
    statusError.value = err.response?.data?.errors?.status?.[0] ?? 'Erro ao atualizar status.'
  } finally {
    updating.value = false
  }
}

function close() {
  store.activeOrder = null
  order.value = null
}

defineExpose({ open })
</script>

<template>
  <transition name="drawer">
    <aside
      v-if="order || loading"
      class="fixed inset-y-0 right-0 z-50 flex w-full flex-col bg-white shadow-2xl sm:w-[480px]"
      role="dialog"
      aria-modal="true"
      aria-label="Detalhes do pedido"
    >
      <!-- Header -->
      <div class="flex items-center justify-between border-b px-5 py-4">
        <h2 class="text-base font-semibold text-gray-900">
          Pedido #{{ order?.id ?? '...' }}
        </h2>
        <button @click="close" class="btn-ghost px-2 py-1" aria-label="Fechar painel">✕</button>
      </div>

      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">
        <!-- Loading skeleton -->
        <template v-if="loading">
          <div class="skeleton h-5 w-40"></div>
          <div class="skeleton h-4 w-64"></div>
          <div class="skeleton h-32 w-full"></div>
        </template>

        <template v-else-if="order">
          <!-- Order info -->
          <section>
            <dl class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <dt class="text-gray-500">Status</dt>
                <dd>
                  <span :class="`badge-${order.status}`">{{ order.status }}</span>
                </dd>
              </div>
              <div>
                <dt class="text-gray-500">Valor total</dt>
                <dd class="font-semibold">{{ fmt.format(order.total_value) }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">Afiliado</dt>
                <dd>{{ order.affiliate?.name }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">Data do pedido</dt>
                <dd>{{ fmtDate(order.created_at) }}</dd>
              </div>
            </dl>
          </section>

          <!-- Items -->
          <section>
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Itens</h3>
            <ul class="divide-y divide-gray-100 rounded-lg border text-sm">
              <li
                v-for="item in order.items"
                :key="item.id"
                class="flex items-start gap-3 px-3 py-2"
              >
                <img
                  v-if="item.product?.image"
                  :src="item.product.image"
                  :alt="item.product?.title"
                  class="h-10 w-10 shrink-0 rounded object-contain"
                />
                <div class="flex-1 min-w-0">
                  <p class="truncate font-medium text-gray-800">{{ item.product?.title }}</p>
                  <p class="text-gray-500">{{ item.quantity }}× {{ fmt.format(item.price) }}</p>
                </div>
                <p class="font-semibold text-gray-900 shrink-0">{{ fmt.format(item.subtotal) }}</p>
              </li>
            </ul>
          </section>

          <!-- Status change -->
          <section v-if="validTransitions.length > 0">
            <h3 class="mb-2 text-sm font-semibold text-gray-700">Alterar status</h3>
            <div class="flex gap-2">
              <select
                v-model="selectedStatus"
                class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                aria-label="Selecionar novo status"
              >
                <option value="">Selecione...</option>
                <option v-for="s in validTransitions" :key="s" :value="s" class="capitalize">{{ s }}</option>
              </select>
              <button
                :disabled="!selectedStatus || updating"
                @click="submitStatus"
                class="btn-primary"
                aria-label="Confirmar mudança de status"
              >
                {{ updating ? '...' : 'Confirmar' }}
              </button>
            </div>
            <p v-if="statusError" class="mt-1 text-xs text-red-600" role="alert">{{ statusError }}</p>
          </section>
          <p v-else class="text-sm text-gray-500">Nenhuma transição disponível para este status.</p>

          <!-- Timeline -->
          <section>
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Histórico de status</h3>
            <StatusTimeline :logs="order.status_logs ?? []" />
          </section>
        </template>
      </div>
    </aside>
  </transition>

  <!-- Backdrop -->
  <transition name="fade">
    <div
      v-if="order || loading"
      class="fixed inset-0 z-40 bg-black/30"
      @click="close"
      aria-hidden="true"
    ></div>
  </transition>
</template>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: transform 0.25s ease; }
.drawer-enter-from, .drawer-leave-to { transform: translateX(100%); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
