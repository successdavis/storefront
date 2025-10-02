<script setup lang="ts">
import {Head, Link, router} from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps<{
  entries: any,
  filters: {
    search?: string,
    type?: string,
    warehouse_id?: string,
    employee_id?: string,
    from?: string,
    to?: string
  },
  warehouses: { id: number; name: string }[],
  employees: { id: number; name: string }[]
}>()

// reactive filters
const search      = ref(props.filters.search || '')
const type        = ref(props.filters.type || '')
const warehouseId = ref(props.filters.warehouse_id || '')
const employeeId  = ref(props.filters.employee_id || '')
const fromDate    = ref(props.filters.from || '')
const toDate      = ref(props.filters.to || '')

function refresh() {
  router.get(
    route('admin.stock-entries.index'),
    {
      search: search.value,
      type: type.value,
      warehouse_id: warehouseId.value,
      employee_id: employeeId.value,
      from: fromDate.value,
      to: toDate.value
    },
    { preserveState: true, replace: true }
  )
}

// Update on any change
watch([search, type, warehouseId, employeeId, fromDate, toDate], refresh)
</script>

<template>
  <Head title="Stock Entries" />

  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Stock Entries</h1>
      <Link
        as="button"
        :href="route('admin.stock-entries.create')"
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        + New Entry
      </Link>
    </div>

    <!-- Filters -->
    <div class="grid md:grid-cols-3 lg:grid-cols-6 gap-4 bg-white p-4 rounded-lg shadow">
      <input v-model="search" type="text"
             placeholder="Search SKU / Product"
             class="border rounded-lg px-3 py-2 w-full" />

      <select v-model="type" class="border rounded-lg px-3 py-2 w-full">
        <option value="">All Types</option>
        <option value="stock_in">Stock In</option>
        <option value="stock_out">Stock Out</option>
      </select>

      <!-- Warehouse -->
      <select v-model="warehouseId" class="border rounded-lg px-3 py-2 w-full">
        <option value="">All Warehouses</option>
        <option v-for="w in props.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
      </select>

      <!-- Employee -->
      <select v-model="employeeId" class="border rounded-lg px-3 py-2 w-full">
        <option value="">All Employees</option>
        <option v-for="e in props.employees" :key="e.id" :value="e.id">{{ e.name }}</option>
      </select>

      <!-- Date Range -->
      <input v-model="fromDate" type="date" class="border rounded-lg px-3 py-2 w-full" />
      <input v-model="toDate" type="date" class="border rounded-lg px-3 py-2 w-full" />
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-white shadow rounded-lg">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left">Date</th>
            <th class="px-4 py-2 text-left">Warehouse</th>
            <th class="px-4 py-2 text-left">Variant</th>
            <th class="px-4 py-2 text-left">Type</th>
            <th class="px-4 py-2 text-right">Qty</th>
            <th class="px-4 py-2 text-right">Unit Cost</th>
            <th class="px-4 py-2 text-right">Total</th>
            <th class="px-4 py-2 text-left">Source</th>
            <th class="px-4 py-2">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="entry in props.entries.data" :key="entry.id">
            <td class="px-4 py-2">{{ entry.effective_at }}</td>
            <td class="px-4 py-2">{{ entry.warehouse?.name ?? '—' }}</td>
            <td class="px-4 py-2">
              {{ entry.variant.product }} ({{ entry.variant.sku }})
            </td>
            <td class="px-4 py-2">
              <span :class="entry.type === 'stock_in' ? 'text-green-600' : 'text-red-600'">
                {{ entry.type.replace('_',' ').toUpperCase() }}
              </span>
            </td>
            <td class="px-4 py-2 text-right">{{ entry.quantity }}</td>
            <td class="px-4 py-2 text-right">{{ entry.unit_cost }}</td>
            <td class="px-4 py-2 text-right">{{ entry.total_cost }}</td>
            <td class="px-4 py-2">{{ entry.reason }}</td>
            <td class="px-4 py-2 text-center">
              <Link :href="route('admin.stock-entries.show', entry.id)" class="text-indigo-600 hover:underline">
                View
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      <Pagination :links="props.entries.links" />
    </div>
  </div>
</template>
