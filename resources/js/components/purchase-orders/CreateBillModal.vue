<template>
    <Dialog :open="open" @update:open="val => !val && emit('close')">
        <DialogContent class="!w-[95vw] !max-w-5xl"> <!-- ! to override library defaults if necessary -->
            <DialogHeader>
                <DialogTitle>Create Vendor Bill</DialogTitle>
            </DialogHeader>

            <!-- modal body: limit height and allow internal scrolling -->
            <div class="modal-body px-4 pt-2 pb-4 overflow-auto" style="max-height: calc(100vh - 6rem);">
                <div class="mb-4">
                    <label class="block text-sm font-medium">Bill Date</label>
                    <input v-model="form.bill_date" type="date" class="mt-1 w-full rounded border px-2 py-1" />
                </div>

                <div v-if="loading" class="text-sm text-muted-foreground">Loading…</div>

                <div v-else>
                    <div v-if="payload.partial_message" class="mb-3 rounded border-l-4 border-yellow-400 bg-yellow-50 p-3 text-sm">
                        {{ payload.partial_message }}
                    </div>

                    <div class="flex items-center gap-3 mb-3">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="billReceivedOnly" />
                            Bill received items only
                        </label>
                        <div class="text-sm text-muted-foreground">Toggle to include outstanding/unreceived items</div>
                    </div>

                    <!-- horizontal scroll wrapper -->
                    <div class="overflow-x-auto">
                        <!-- inline-block + min-w-full keeps the table from collapsing -->
                        <div class="inline-block min-w-full align-middle">
                            <table class="min-w-full table-fixed text-sm border-collapse">
                                <thead class="bg-gray-50 sticky top-0 z-20">
                                <tr>
                                    <!-- set fixed column widths so table-fixed works predictably -->
                                    <th class="p-2 text-left w-2/5">SKU / Title</th>
<!--                                    <th class="p-2 text-right w-1/12">Ordered</th>-->
                                    <th class="p-2 text-right w-1/12">Unit Cost</th>
<!--                                    <th class="p-2 text-right w-1/12">Billed</th>-->
                                    <th class="p-2 text-right w-1/12">Remaining</th>
                                    <th class="p-2 text-right w-1/6">To bill</th>
                                </tr>
                                </thead>

                                <tbody>
                                <tr v-for="(item, idx) in payload.items" :key="item.purchase_order_item_id" class="border-t align-top">
                                    <td class="p-2 align-top whitespace-normal break-words">
                                        <div class="font-medium whitespace-normal break-words">{{ item.title || item.sku }}</div>
                                        <div class="text-xs text-muted-foreground whitespace-normal break-words">{{ item.sku }}</div>
                                    </td>

<!--                                    <td class="p-2 text-right align-top">{{ item.ordered }}</td>-->
                                    <td class="p-2 text-right align-top">
                                        <input
                                                type="number"
                                                :min="0"
                                                step="0.0001"
                                                class="w-20 rounded border px-2 py-1 text-right"
                                                v-model.number="form.items[idx].unit_cost"
                                            />
                                    </td>
<!--                                    <td class="p-2 text-right align-top">{{ formatDecimal(item.billed) }}</td>-->
                                    <td class="p-2 text-right align-top">{{ item.remaining_ordered }}</td>

                                    <td class="p-2 text-right align-top">
                                        <div class="flex items-center justify-end gap-2">
                                            <input
                                                type="number"
                                                :min="0"
                                                step="0.0001"
                                                class="w-20 rounded border px-2 py-1 text-right"
                                                v-model.number="form.items[idx].quantity"
                                                :max="maxBillableFor(item)"
                                            />
                                            <div class="text-xs text-muted-foreground">/ {{ maxBillableFor(item) }}</div>
                                        </div>

                                        <div v-if="item.billable_received > 0 && item.billable_unreceived > 0" class="text-xs text-muted-foreground mt-1">
                                            Received billable: {{ item.billable_received }}, Unreceived billable: {{ item.billable_unreceived }}
                                        </div>
                                        <div v-if="maxBillableFor(item) === 0" class="text-xs text-red-600 mt-1">No billable quantity</div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Additional expenses and rest of UI remain unchanged -->
                    <h3 class="text-sm font-semibold mt-6 mb-2">Additional Expenses</h3>
                    <div v-for="(exp, idx) in form.expenses" :key="idx" class="flex gap-2 mb-2">
                        <input v-model="exp.description" placeholder="Description" class="flex-1 rounded border px-2 py-1" />
                        <input v-model.number="exp.amount" type="number" min="0" step="0.01" placeholder="Amount" class="w-32 rounded border px-2 py-1 text-right" />
                        <button type="button" class="text-red-500" @click="form.expenses.splice(idx,1)">✕</button>
                    </div>
                    <Button size="sm" variant="secondary" @click="addExpense">+ Add Expense</Button>
                </div>
            </div>

            <DialogFooter class="mt-6">
                <div class="flex items-center justify-between w-full">
                    <div class="text-sm text-muted-foreground">Total: {{ formatCurrency(computedTotal) }}</div>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="emit('close')">Cancel</Button>
                        <Button :disabled="submitting || totalToBill <= 0" @click="submit">{{ submitting ? 'Saving…' : 'Create Bill' }}</Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>

    </Dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';

interface Props {
    open: boolean;
    orderId: number;
}
const props = defineProps<Props>();
const emit = defineEmits(['close','success']);

const loading = ref(true);
const submitting = ref(false);
const payload = ref({
    purchase_order: null,
    items: [] as any[],
    partial_message: null as string | null,
});

const billReceivedOnly = ref(true);

const form = ref({
    bill_date: new Date().toISOString().slice(0,10),
    vendor_id: null as number | null,
    purchase_order_id: props.orderId,
    items: [] as Array<{
        purchase_order_item_id: number | null;
        product_variant_id: number | null;
        description: string;
        quantity: number;
        unit_cost: number;
        product_id?: number | null;
    }>,
    expenses: [] as { description: string; amount: number }[],
});

const load = async () => {
    loading.value = true;
    try {
        const res = await axios.get(route('admin.purchase-orders.item-receipts-for-billing', props.orderId));
        payload.value = res.data;
        // set vendor id from PO if present
        if (payload.value.purchase_order) {
            form.value.vendor_id = payload.value.purchase_order.vendor_id ?? null;
            form.value.purchase_order_id = payload.value.purchase_order.id ?? props.orderId;
        }

        // initialize form.items mapping to payload.items
        form.value.items = payload.value.items.map((it: any) => {
            const max = Math.max(0, (billReceivedOnly.value ? it.billable_received : it.billable_received + it.billable_unreceived));
            return {
                purchase_order_item_id: it.purchase_order_item_id,
                product_variant_id: it.product_variant_id,
                description: it.title || it.sku || 'Item',
                quantity: Math.min(max, max > 0 ? max : 0),
                unit_cost: it.unit_cost ?? 0,
                product_id: null,
            };
        });
        // if there are items with zero billable, set quantity to 0
    } catch (err) {
        console.error(err);
        window.alert('Could not load billable items.');
    } finally {
        loading.value = false;
    }
};

onMounted(load);

// whenever toggle changes, adjust form.items quantity max to sensible default
watch(billReceivedOnly, (v) => {
    if (!payload.value || !payload.value.items) return;
    payload.value.items.forEach((it: any, idx: number) => {
        const max = Math.max(0, (v ? it.billable_received : (it.billable_received + it.billable_unreceived)));
        // clamp current quantity if above max
        if (form.value.items[idx]) {
            form.value.items[idx].quantity = Math.min(form.value.items[idx].quantity, max);
        }
    });
});

// helpers
function addExpense() {
    form.value.expenses.push({ description: '', amount: 0 });
}

function maxBillableFor(item: any) {
    return billReceivedOnly.value ? Number(item.billable_received) : Number(item.billable_received) + Number(item.billable_unreceived);
}

const totalToBill = computed(() => {
    return form.value.items.reduce((s, it) => s + (Number(it.quantity || 0) * Number(it.unit_cost || 0)), 0)
        + form.value.expenses.reduce((s, e) => s + (Number(e.amount || 0)), 0);
});

const computedTotal = computed(() => totalToBill.value);

function formatCurrency(v: number) {
    return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
}
function formatDecimal(v: any) {
    return Number(v || 0).toFixed(4).replace(/\.?0+$/, '');
}

async function submit() {
    // client-side sanity: ensure no quantity > allowed
    for (let i = 0; i < payload.value.items.length; i++) {
        const it = payload.value.items[i];
        const max = maxBillableFor(it);
        const q = form.value.items[i].quantity || 0;
        if (q < 0 || q > max + 1e-8) {
            window.alert(`Quantity for item ${it.title || it.sku} must be between 0 and ${max}`);
            return;
        }
    }

    const itemsToSend = form.value.items.filter(it => Number(it.quantity) > 0).map(it => ({
        purchase_order_item_id: it.purchase_order_item_id,
        product_variant_id: it.product_variant_id,
        description: it.description,
        quantity: Number(it.quantity),
        unit_cost: Number(it.unit_cost),
    }));

    if (itemsToSend.length === 0) {
        window.alert('Please enter at least one quantity to bill.');
        return;
    }

    submitting.value = true;
    try {
        const payloadToSend = {
            purchase_order_id: form.value.purchase_order_id,
            vendor_id: form.value.vendor_id,
            bill_date: form.value.bill_date,
            items: itemsToSend,
            expenses: form.value.expenses.filter(e => e.amount > 0 && e.description),
        };

        await axios.post(route('admin.vendor-bills.store'), payloadToSend);
        emit('success');
        emit('close');
    } catch (err: any) {
        // show server error message if present
        if (err?.response?.data?.message) {
            console.log(err.response.data.message);
        } else {
            console.log('Could not create bill.');
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<style scoped>
.text-muted-foreground { color: rgba(100,116,139,1); }
</style>
