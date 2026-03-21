<script setup lang="ts">
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer'
import { Button } from '@/components/ui/button'
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { Printer } from 'lucide-vue-next'

interface Sale {
    id: number
    total_amount: string
    customer_name: string
    time: string // "HH:MM:SS"
}

const isOpen = ref(false)
const salesOrders = ref<Sale[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = usePage()

watch(isOpen, async (val) => {
    if (val) {
        await fetchSalesOrders()
    }
})

async function fetchSalesOrders() {
    loading.value = true
    error.value = null
    try {
        const response = await axios.get(page.props.pos_routes.sales_orders)
        // Normalize response: response.data.data expected
        salesOrders.value = response.data?.data ?? []
    } catch (err: any) {
        console.error(err)
        error.value = 'Failed to load sales orders.'
    } finally {
        loading.value = false
    }
}

async function printReceipt(saleId) {
    const url = String(page.props.pos_routes.print_sale_template).replace('__SALE__', String(saleId));
    const printWindow = window.open(url, '_blank');
    printWindow.focus();

    // Optional: trigger print automatically once loaded
    printWindow.onload = () => {
        printWindow.print();
    };
}
</script>

<template>
    <Drawer direction="right" v-model:open="isOpen">
        <DrawerTrigger>Sales</DrawerTrigger>

        <DrawerContent class="bottom-2 left-auto right-2 top-2 mt-0 w-[450px] overflow-hidden rounded-[10px]">
            <DrawerHeader>
                <DrawerTitle>Today's Sales Log</DrawerTitle>
            </DrawerHeader>

            <div class="p-4 h-[70vh] overflow-y-auto">
                <div v-if="loading" class="text-sm text-gray-500">Loading sales orders...</div>
                <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
                <div v-else-if="salesOrders.length === 0" class="text-sm text-gray-400">No sales found for today.</div>

                <div v-else class="space-y-3">
                    <div
                        v-for="order in salesOrders"
                        :key="order.id"
                        class="flex justify-between items-center rounded-md border p-3 hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <div>
                            <p class="font-medium text-sm">Order #{{ order.id }} — ₦{{ Number(order.total_amount).toLocaleString() }}</p>
                            <p class="text-xs text-muted-foreground">{{ order.customer_name }}</p>
                            <p class="text-xs text-muted-foreground">Time: {{ order.time }}</p>
                        </div>

                        <Button size="icon" variant="ghost" @click="printReceipt(order.id)" title="Print Receipt" class="cursor-pointer">
                            <Printer class="w-8 h-8" />
                        </Button>
                    </div>
                </div>
            </div>

            <DrawerFooter>
                <Button>Submit</Button>
                <DrawerClose>
                    <Button variant="outline">Cancel</Button>
                </DrawerClose>
            </DrawerFooter>
        </DrawerContent>
    </Drawer>
</template>
