<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3'

import {
    DollarSign,
    Store,
    ShoppingCart,
    ClipboardList,
    Calendar,
} from 'lucide-vue-next';

import KpiCard from '@/Components/Dashboard/KpiCard.vue';
import SalesChart from '@/Components/Dashboard/SalesChart.vue';
import TransactionsTable from '@/Components/Dashboard/TransactionsTable.vue';
import InventoryAlerts from '@/Components/Dashboard/InventoryAlerts.vue';
import TerminalStatus from '@/Components/Dashboard/TerminalStatus.vue';
import DateRangeFilter from '@/Components/Dashboard/DateRangeFilter.vue';
import axios from 'axios';

const props = defineProps({
    stats: Object,
    sales: Array,
    transactions: Array,
    inventoryAlerts: Array,
    terminals: Array,
});

const range = ref('today');
const sales = ref(props.sales);

const stats = ref(props.stats)

watch(range, async value => {
    const { data } = await axios.get('/admin/dashboard/kpis', {
        params: { range: value },
    })

    stats.value = data

    const chart = await axios.get('/admin/dashboard/sales-chart', {
        params: { range: value },
    })
    sales.value = chart.data
}, { immediate: true })

const rangeLabels = {
    today: {
        prefix: 'Today',
        date: () =>
            new Date().toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            }),
    },

    yesterday: {
        prefix: 'Yesterday',
        date: () => {
            const d = new Date();
            d.setDate(d.getDate() - 1);
            return d.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
        },
    },

    last_7_days: {
        prefix: 'Last 7 Days',
        date: () => {
            const end = new Date();
            const start = new Date();
            start.setDate(end.getDate() - 6);

            return `${start.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
            })} – ${end.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            })}`;
        },
    },

    this_month: {
        prefix: 'This Month',
        date: () =>
            new Date().toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric',
            }),
    },

    last_month: {
        prefix: 'Last Month',
        date: () => {
            const d = new Date();
            d.setMonth(d.getMonth() - 1);
            return d.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric',
            });
        },
    },

    last_three_months: {
        prefix: 'Last 3 Months',
        date: () => {
            const end = new Date();
            const start = new Date();
            start.setMonth(end.getMonth() - 2);

            return `${start.toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
            })} – ${end.toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
            })}`;
        },
    },

    last_six_months: {
        prefix: 'Last 6 Months',
        date: () => {
            const end = new Date();
            const start = new Date();
            start.setMonth(end.getMonth() - 5);

            return `${start.toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
            })} – ${end.toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
            })}`;
        },
    },

    this_year: {
        prefix: 'This Year',
        date: () => new Date().getFullYear().toString(),
    },

    all_time: {
        prefix: 'All Time',
        date: () => 'All Records',
    },
};


const headerTitle = computed(() => {
    const config = rangeLabels[range.value];
    return `Overview – ${config.prefix}, ${config.date()}`;
});
</script>

<template>
    <div class="min-h-screen space-y-6 bg-gray-100 p-6 dark:bg-gray-950">
        <!-- HEADER -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                {{ headerTitle }}
            </h1>

            <div class="flex items-center gap-3">
                <DateRangeFilter v-model="range" />
            </div>
        </div>

        <!-- KPI GRID -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard
                title="Total Revenue"
                :value="stats.totalRevenue.value"
                :subtitle="stats.totalRevenue.subtitle + ' ' + stats.totalRevenue.compare_label"
                :icon="DollarSign"
            />

            <KpiCard
                title="In-Store Sales (POS)"
                :value="stats.posSales.value"
                :progress="stats.posSales.progress"
                :icon="Store"
            />

            <KpiCard
                title="Online Sales"
                :value="stats.onlineSales.value"
                :progress="stats.onlineSales.progress"
                :icon="ShoppingCart"
            />

            <KpiCard
                title="Total Orders"
                :value="stats.totalOrders.value"
                :subtitle="stats.totalOrders.subtitle"
                :icon="ClipboardList"
            />
        </div>

        <!-- SALES CHART -->
        <SalesChart :data="sales" />

        <!-- BOTTOM GRID -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <TransactionsTable
                class="lg:col-span-2"
                :transactions="transactions"
            />

            <div class="space-y-6">
                <InventoryAlerts :alerts="inventoryAlerts" />
                <TerminalStatus :terminals="terminals" />
            </div>
        </div>
    </div>
</template>
