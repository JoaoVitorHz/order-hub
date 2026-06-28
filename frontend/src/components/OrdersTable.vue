<script setup>
import { ref, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useOrdersStore } from '@/stores/ordersStore'

const emit = defineEmits(['openOrder'])
const store = useOrdersStore()
const route = useRoute()
const router = useRouter()

const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDate = (iso) => new Intl.DateTimeFormat('pt-BR').format(new Date(iso))

// Filters — initialized from URL query params
const filters = ref({
  affiliate_id: route.query.affiliate_id ?? '',
  status: route.query.status ?? '',
  date_from: route.query.date_from ?? '',
  date_to: route.query.date_to ?? '',
  min_value: route.query.min_value ?? '',
  max_value: route.query.max_value ?? '',
})
const page = ref(Number(route.query.page) || 1)
const sortBy = ref(route.query.sort_by ?? 'created_at')
const sortDir = ref(route.query.sort_dir ?? 'desc')

const allSelected = computed(
  () => store.orders.length > 0 && store.orders.every((o) => store.selectedIds.has(o.id)),
)

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'affiliate', label: 'Afiliado', sortable: false },
  { key: 'total_value', label: 'Valor', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'created_at', label: 'Data', sortable: true },
  { key: 'actions', label: 'Ações', sortable: false },
]

function buildParams() {
  const p = {}
  Object.entries(filters.value).forEach(([k, v]) => { if (v !== '') p[k] = v })
  p.page = page.value
  p.sort_by = sortBy.value
  p.sort_dir = sortDir.value
  return p
}

async function load() {
  const params = buildParams()
  await store.loadOrders(params)
  // Sync to URL (source of truth)
  await router.replace({ query: params })
}

const debouncedLoad = useDebounceFn(load, 400)

watch(filters, () => { page.value = 1; debouncedLoad() }, { deep: true })
watch([page, sortBy, sortDir], load)

// Initial load
load()

function sort(col) {
  if (!col.sortable) return
  if (sortBy.value === col.key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = col.key
    sortDir.value = 'asc'
  }
}

function toggleAll() {
  if (allSelected.value) store.clearSelection()
  else store.selectAll()
}

async function bulkCancel() {
  const ids = [...store.selectedIds]
  for (const id of ids) {
    try {
      await store.changeStatus(id, 'cancelled')
    } catch { /* skip non-cancellable */ }
  }
  store.clearSelection()
  await load()
}
</script>

<template>
  <div class="space-y-4">
    <!-- Filters -->
    <div class="card p-4">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Afiliado ID</label>
          <input
            v-model="filters.affiliate_id"
            type="number"
            min="1"
            placeholder="Ex: 3"
            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
            aria-label="Filtrar por ID do afiliado"
          />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
          <select
            v-model="filters.status"
            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
            aria-label="Filtrar por status"
          >
            <option value="">Todos</option>
            <option value="pending">Pendente</option>
            <option value="approved">Aprovado</option>
            <option value="cancelled">Cancelado</option>
            <option value="refunded">Reembolsado</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">De</label>
          <input v-model="filters.date_from" type="date"
            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
            aria-label="Data inicial" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Até</label>
          <input v-model="filters.date_to" type="date"
            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
            aria-label="Data final" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Valor mín.</label>
          <input v-model="filters.min_value" type="number" min="0" step="0.01" placeholder="0"
            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
            aria-label="Valor mínimo" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Valor máx.</label>
          <input v-model="filters.max_value" type="number" min="0" step="0.01" placeholder="∞"
            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
            aria-label="Valor máximo" />
        </div>
      </div>
    </div>

    <!-- Bulk action bar -->
    <div v-if="store.selectedCount > 0" class="flex items-center gap-3 rounded-lg bg-blue-50 px-4 py-2 text-sm">
      <span class="font-medium text-blue-700">{{ store.selectedCount }} pedido(s) selecionado(s)</span>
      <button @click="bulkCancel" class="btn-danger text-xs" aria-label="Cancelar pedidos selecionados">
        Cancelar selecionados
      </button>
      <button @click="store.clearSelection" class="btn-ghost text-xs">Limpar seleção</button>
    </div>

    <!-- Desktop table -->
    <div class="card hidden overflow-hidden md:block">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
            <tr>
              <th class="px-4 py-3 text-left">
                <input
                  type="checkbox"
                  :checked="allSelected"
                  @change="toggleAll"
                  class="rounded border-gray-300"
                  aria-label="Selecionar todos os pedidos"
                />
              </th>
              <th
                v-for="col in columns"
                :key="col.key"
                class="px-4 py-3 text-left"
                :class="{ 'cursor-pointer select-none hover:bg-gray-100': col.sortable }"
                @click="sort(col)"
                :aria-sort="col.sortable && sortBy === col.key ? sortDir === 'asc' ? 'ascending' : 'descending' : 'none'"
              >
                <span class="flex items-center gap-1">
                  {{ col.label }}
                  <template v-if="col.sortable">
                    <span v-if="sortBy === col.key" aria-hidden="true">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                    <span v-else class="text-gray-300" aria-hidden="true">↕</span>
                  </template>
                </span>
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
            <!-- Loading rows -->
            <template v-if="store.loading">
              <tr v-for="i in 5" :key="i" class="animate-pulse">
                <td class="px-4 py-3"><div class="skeleton h-4 w-4 rounded"></div></td>
                <td class="px-4 py-3" v-for="j in 5" :key="j"><div class="skeleton h-4 w-full max-w-[80px]"></div></td>
                <td class="px-4 py-3"><div class="skeleton h-6 w-16 rounded-full"></div></td>
              </tr>
            </template>

            <!-- Empty state -->
            <tr v-else-if="store.orders.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                <p class="text-base font-medium">Nenhum pedido encontrado</p>
                <p class="mt-1 text-sm">Tente ajustar os filtros aplicados.</p>
              </td>
            </tr>

            <!-- Data rows -->
            <tr
              v-else
              v-for="order in store.orders"
              :key="order.id"
              class="transition-colors hover:bg-gray-50"
            >
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  :checked="store.selectedIds.has(order.id)"
                  @change="store.toggleSelect(order.id)"
                  class="rounded border-gray-300"
                  :aria-label="`Selecionar pedido ${order.id}`"
                />
              </td>
              <td class="px-4 py-3 font-mono text-gray-700">#{{ order.id }}</td>
              <td class="px-4 py-3 text-gray-800">{{ order.affiliate?.name ?? '—' }}</td>
              <td class="px-4 py-3 font-semibold text-gray-900">{{ fmt.format(order.total_value) }}</td>
              <td class="px-4 py-3">
                <span :class="`badge-${order.status}`">{{ order.status }}</span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ fmtDate(order.created_at) }}</td>
              <td class="px-4 py-3">
                <button
                  @click="emit('openOrder', order.id)"
                  class="btn-ghost text-xs"
                  :aria-label="`Ver detalhes do pedido ${order.id}`"
                >
                  Detalhes
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="store.meta?.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-sm text-gray-600">
        <span>Página {{ store.meta.current_page }} de {{ store.meta.last_page }} — {{ store.meta.total }} pedidos</span>
        <div class="flex gap-2">
          <button
            :disabled="page <= 1"
            @click="page--"
            class="btn-ghost text-xs"
            aria-label="Página anterior"
          >← Anterior</button>
          <button
            :disabled="page >= store.meta.last_page"
            @click="page++"
            class="btn-ghost text-xs"
            aria-label="Próxima página"
          >Próxima →</button>
        </div>
      </div>
    </div>

    <!-- Mobile cards (below md) -->
    <div class="space-y-3 md:hidden">
      <template v-if="store.loading">
        <div v-for="i in 4" :key="i" class="card p-4 space-y-2">
          <div class="skeleton h-4 w-3/4"></div>
          <div class="skeleton h-4 w-1/2"></div>
          <div class="skeleton h-6 w-24 rounded-full"></div>
        </div>
      </template>

      <div v-else-if="store.orders.length === 0" class="card p-8 text-center text-gray-500">
        <p class="font-medium">Nenhum pedido encontrado</p>
        <p class="mt-1 text-sm">Tente ajustar os filtros.</p>
      </div>

      <div
        v-else
        v-for="order in store.orders"
        :key="order.id"
        class="card p-4"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="font-mono text-sm font-semibold text-gray-700">#{{ order.id }}</p>
            <p class="text-sm text-gray-800">{{ order.affiliate?.name ?? '—' }}</p>
          </div>
          <span :class="`badge-${order.status}`">{{ order.status }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between">
          <p class="font-bold text-gray-900">{{ fmt.format(order.total_value) }}</p>
          <p class="text-xs text-gray-500">{{ fmtDate(order.created_at) }}</p>
        </div>
        <button
          @click="emit('openOrder', order.id)"
          class="btn-ghost mt-3 w-full justify-center text-xs"
          :aria-label="`Ver detalhes do pedido ${order.id}`"
        >
          Ver detalhes
        </button>
      </div>

      <!-- Mobile pagination -->
      <div v-if="store.meta?.last_page > 1" class="flex justify-between text-sm">
        <button :disabled="page <= 1" @click="page--" class="btn-ghost" aria-label="Página anterior">← Anterior</button>
        <span class="py-2 text-gray-500">{{ page }} / {{ store.meta.last_page }}</span>
        <button :disabled="page >= store.meta.last_page" @click="page++" class="btn-ghost" aria-label="Próxima página">Próxima →</button>
      </div>
    </div>
  </div>
</template>
