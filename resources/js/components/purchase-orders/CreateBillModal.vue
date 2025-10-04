<template>
    <Dialog :open="open" @update:open="val => !val && emit('close')">
        <DialogContent class="!w-[95vw] !max-w-5xl">
            <DialogHeader>
                <DialogTitle>Create Vendor Bill</DialogTitle>
            </DialogHeader>

            <!-- modal body -->
            <div
                class="modal-body px-4 pt-2 pb-4 overflow-auto"
                style="max-height: calc(100vh - 6rem);"
            >
                <div class="mb-4">
                    <label class="block text-sm font-medium">Bill Date</label>
                    <input
                        v-model="form.bill_date"
                        type="date"
                        class="mt-1 w-full rounded border px-2 py-1"
                    />
                </div>

                <div v-if="loading" class="text-sm text-muted-foreground">Loading…</div>

                <div v-else>
                    <!-- Dropdown for receipts -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Select Item Receipt</label>
                        <select
                            v-model="selectedReceiptId"
                            class="mt-1 w-full rounded border px-2 py-1"
                        >
                            <option disabled value="">-- Choose a receipt --</option>
                            <option
                                v-for="receipt in payload.item_receipts"
                                :key="receipt.id"
                                :value="receipt.id"
                            >
                                Receipt #{{ receipt.receipt_number }} — {{ receipt.received_date }}
                            </option>
                        </select>
                    </div>

                    <!-- If there are no receipts -->
                    <div
                        v-if="!payload.item_receipts || payload.item_receipts.length === 0"
                        class="p-4 rounded border bg-gray-50 text-sm text-muted-foreground"
                    >
                        There are no item receipts available for billing.
                    </div>

                    <!-- If a receipt is selected, show its items -->
                    <div v-else>
                        <div v-if="selectedReceiptItems.length > 0" class="overflow-x-auto">
                            <div class="inline-block min-w-full align-middle">
                                <table
                                    class="min-w-full table-fixed text-sm border-collapse"
                                >
                                    <thead class="bg-gray-50 sticky top-0 z-20">
                                    <tr>
                                        <th class="p-2 text-left w-2/5">SKU / Title</th>
                                        <th class="p-2 text-right w-1/12">Unit Cost</th>
                                        <th class="p-2 text-right w-1/12">Qty Received</th>
                                        <th class="p-2 text-right w-1/6">Total</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    <tr
                                        v-for="(item, idx) in selectedReceiptItems"
                                        :key="item.id"
                                        class="border-t align-top"
                                    >
                                        <td class="p-2 align-top whitespace-normal break-words">
                                            <div class="font-medium whitespace-normal break-words">
                                                {{ item.product_variant.product.name + '-' + item.product_variant.sku || item.sku }}
                                            </div>
                                        </td>

                                        <td class="p-2 text-right align-top">
                                            <input
                                                type="number"
                                                :min="0"
                                                step="0.0001"
                                                class="w-20 rounded border px-2 py-1 text-right"
                                                v-model.number="item.unit_cost"
                                            />
                                        </td>

                                        <td class="p-2 text-right align-top">
                                            <div class="flex items-center justify-end gap-2">
                                                <input
                                                    type="number"
                                                    :min="0"
                                                    :max="item.quantity_received"
                                                    step="0.0001"
                                                    class="w-20 rounded border px-2 py-1 text-right"
                                                    v-model.number="item.quantity"
                                                />
                                                <div class="text-xs text-muted-foreground">
                                                    / {{ item.quantity_received }}
                                                </div>
                                            </div>
                                        </td>

                                        <td class="p-2 text-right align-top">
                                            <div class="text">
                                                {{ formatCurrency(item.unit_cost * item.quantity) }}
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            v-else
                            class="p-4 rounded border bg-gray-50 text-sm text-muted-foreground"
                        >
                            Select an item receipt to view received items.
                        </div>

                        <!-- Additional expenses -->
                        <h3 class="text-sm font-semibold mt-6 mb-2">Additional Expenses</h3>
                        <div
                            v-for="(exp, idx) in form.expenses"
                            :key="idx"
                            class="flex gap-2 mb-2"
                        >
                            <input
                                v-model="exp.description"
                                placeholder="Description"
                                class="flex-1 rounded border px-2 py-1"
                            />
                            <input
                                v-model.number="exp.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Amount"
                                class="w-32 rounded border px-2 py-1 text-right"
                            />
                            <button
                                type="button"
                                class="text-red-500"
                                @click="form.expenses.splice(idx, 1)"
                            >
                                ✕
                            </button>
                        </div>
                        <Button size="sm" variant="secondary" @click="addExpense">
                            + Add Expense
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-6">
                <div class="flex items-center justify-between w-full">
                    <div class="text-sm text-muted-foreground">
                        Total: {{ formatCurrency(computedTotal) }}
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="handleClose">Cancel</Button>
                        <Button
                            :disabled="submitting || itemsToSend.length === 0"
                            @click="submit"
                        >
                            {{ submitting ? "Saving…" : "Create Bill" }}
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from "vue";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import axios from "axios";
import { defineProps } from "vue";

interface Props {
    open: boolean;
    orderId: number;
}
const props = defineProps<Props>();
const emit = defineEmits(["close", "success"]);

const loading = ref(true);
const submitting = ref(false);
const serverError = ref<string | null>(null);

const payload = ref({
    purchase_order: null as any,
    item_receipts: [] as any[],
});

const form = ref({
    bill_date: new Date().toISOString().slice(0, 10),
    vendor_id: null as number | null,
    purchase_order_id: props.orderId,
    expenses: [] as { description: string; amount: number }[],
});

// Receipt dropdown selection
const selectedReceiptId = ref<number | null>(null);

const selectedReceiptItems = computed(() => {
    if (!selectedReceiptId.value) return [];
    const receipt = payload.value.item_receipts.find(
        (r: any) => r.id === selectedReceiptId.value
    );
    if (!receipt) return [];
    // Add editable fields (quantity/unit_cost) if not yet present
    return receipt.items.map((it: any) => ({
        ...it,
        quantity: it.quantity_received,
        unit_cost: it.unit_cost,
    }));
});

// load receipts
async function load() {
    loading.value = true;
    serverError.value = null;
    try {
        const res = await axios.get(
            route("admin.purchase-orders.get-item-receipts", props.orderId)
        );
        payload.value = res.data;

        if (payload.value.purchase_order) {
            form.value.vendor_id = payload.value.purchase_order.vendor_id ?? null;
            form.value.purchase_order_id =
                payload.value.purchase_order.id ?? props.orderId;
        }
    } catch (err) {
        console.error(err);
        serverError.value = "Could not load receipts.";
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (props.open) load();
});
watch(
    () => props.open,
    (v) => {
        if (v) load();
        else resetForm();
    }
);

function resetForm() {
    form.value.expenses = [];
    form.value.bill_date = new Date().toISOString().slice(0, 10);
    payload.value = { purchase_order: null, item_receipts: [] };
    selectedReceiptId.value = null;
    loading.value = true;
    submitting.value = false;
    serverError.value = null;
}

function addExpense() {
    form.value.expenses.push({ description: "", amount: 0 });
}

const itemsToSend = computed(() => {
    return selectedReceiptItems.value
        .filter((it: any) => Number(it.quantity || 0) > 0)
        .map((it: any) => ({
            purchase_order_item_id: it.purchase_order_item_id,
            product_variant_id: it.product_variant_id,
            description: it.product_variant.product.name || it.sku || "",
            quantity: Number(it.quantity),
            unit_cost: Number(it.unit_cost),
        }));
});

const computedTotal = computed(() => {
    return (
        selectedReceiptItems.value.reduce(
            (s, it) => s + Number(it.quantity || 0) * Number(it.unit_cost || 0),
            0
        ) +
        form.value.expenses.reduce((s, e) => s + Number(e.amount || 0), 0)
    );
});

function formatCurrency(v: number) {
    return new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: "NGN",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(v || 0));
}

async function submit() {
    if (!selectedReceiptId.value) {
        window.alert("Please select a receipt first.");
        return;
    }

    if (
        itemsToSend.value.length === 0 &&
        form.value.expenses.filter((e) => e.amount > 0 && e.description).length ===
        0
    ) {
        window.alert("Please enter at least one quantity or add an expense.");
        return;
    }

    submitting.value = true;
    try {
        const payloadToSend = {
            purchase_order_id: form.value.purchase_order_id,
            vendor_id: form.value.vendor_id,
            bill_date: form.value.bill_date,
            receipt_id: selectedReceiptId.value,
            items: itemsToSend.value,
            expenses: form.value.expenses.filter(
                (e) => e.amount > 0 && e.description
            ),
        };

        await axios.post(route("admin.vendor-bills.store"), payloadToSend);
        emit("success");
        handleClose();
    } catch (err: any) {
        const msg = err?.response?.data?.message ?? "Could not create bill.";
        console.error(err);
        window.alert(msg);
    } finally {
        submitting.value = false;
    }
}

function handleClose() {
    emit("close");
}
</script>

<style scoped>
.text-muted-foreground {
    color: rgba(100, 116, 139, 1);
}
</style>
