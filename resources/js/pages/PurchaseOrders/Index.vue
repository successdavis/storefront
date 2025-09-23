<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

// shadcn-vue components
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import Pagination from '@/components/Pagination.vue';

// icons (lucide-vue)
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    purchaseOrders: any;
    filters: {
        search?: string;
        status?: string;
        vendor_id?: string;
        warehouse_id?: string;
        per_page?: string;
    };
    statuses: { value: string; label: string }[];
    vendors: { id: number; name: string }[];
    warehouses: { id: number; name: string }[];
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const vendorId = ref(props.filters.vendor_id ?? 'all');
const warehouse = ref(props.filters.warehouse_id ?? 'all');
const perPage = ref(props.filters.per_page ?? '10');

watch([search, status, vendorId, warehouse, perPage], () => {
    router.get(
        route('admin.purchase-orders.index'),
        {
            search: search.value,
            status: status.value === 'all' ? null : status.value,
            vendor_id: vendorId.value === 'all' ? null : vendorId.value,
            warehouse_id: warehouse.value === 'all' ? null : warehouse.value,
            per_page: perPage.value,
        },
        { preserveState: true, replace: true },
    );
});

// actions
function viewOrder(id: number) {
    router.visit(route('admin.purchase-orders.show', id));
}
function editOrder(id: number) {
    console.log('editing');
    // router.visit(route('admin.purchase-orders.edit', id))
}
function deleteOrder(id: number) {
    if (confirm('Are you sure you want to delete this purchase order?')) {
        // router.delete(route('purchase-orders.destroy', id))
    }
}
</script>

<template>
    <div class="mx-auto max-w-7xl p-6 md:min-w-6xl">
        <Card class="mx-auto space-y-4">
            <CardHeader>
                <CardTitle>Purchase Orders</CardTitle>
            </CardHeader>

            <CardContent class="space-y-4">
                <!-- Filters -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <Input
                        v-model="search"
                        placeholder="Search..."
                        class="md:col-span-2"
                    />

                    <Select v-model="status">
                        <SelectTrigger>
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem
                                v-for="s in statuses"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>

                    <Select v-model="vendorId">
                        <SelectTrigger>
                            <SelectValue placeholder="Vendor" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Vendors</SelectItem>
                            <SelectItem
                                v-for="v in vendors"
                                :key="v.id"
                                :value="String(v.id)"
                            >
                                {{ v.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="warehouse">
                        <SelectTrigger>
                            <SelectValue placeholder="Warehouse" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Warehouses</SelectItem>
                            <SelectItem
                                v-for="w in warehouses"
                                :key="w.id"
                                :value="String(w.id)"
                            >
                                {{ w.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="perPage">
                        <SelectTrigger>
                            <SelectValue placeholder="Per Page" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="10">10</SelectItem>
                            <SelectItem value="25">25</SelectItem>
                            <SelectItem value="50">50</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Table -->
                <Table class="mt-6 w-full">
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Vendor</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Warehouse</TableHead>
                            <TableHead>Total</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead class="text-right">Action</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow
                            v-for="po in purchaseOrders.data"
                            :key="po.id"
                        >
                            <TableCell>{{ po.id }}</TableCell>
                            <TableCell>{{ po.vendor?.name }}</TableCell>
                            <TableCell>{{ po.status }}</TableCell>
                            <TableCell>{{
                                po.warehouse?.name ?? '—'
                            }}</TableCell>
                            <TableCell>{{ po.total | currency }}</TableCell>
                            <TableCell>{{ po.created_at }}</TableCell>
                            <TableCell class="flex justify-end space-x-3">
                                <!-- View -->
                                <Eye
                                    class="h-5 w-5 cursor-pointer text-blue-600 hover:text-blue-800"
                                    @click="viewOrder(po.id)"
                                />

                                <!-- Edit -->
                                <Pencil
                                    class="h-5 w-5 cursor-pointer text-green-600 hover:text-green-800"
                                    @click="editOrder(po.id)"
                                />

                                <!-- Delete -->
                                <Trash2
                                    class="h-5 w-5 cursor-pointer text-red-600 hover:text-red-800"
                                    @click="deleteOrder(po.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="mt-4 flex justify-end">
                    <Pagination :links="purchaseOrders.links" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
