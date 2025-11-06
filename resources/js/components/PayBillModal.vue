<template>
    <Dialog :open="open" @close="$emit('close')">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Pay Vendor Bill</DialogTitle>
                <DialogDescription>
                    Record a payment against one of the vendor bills.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitPayment" class="space-y-4">
                <!-- Bill selection -->
                <div>
                    <label class="block text-sm font-medium mb-1">Select Bill</label>
                    <select
                        v-model="form.bill_id"
                        class="w-full rounded border px-3 py-2 text-sm"
                        required
                    >
                        <option class="dark:text-black" disabled value="">-- Select a bill --</option>
                        <option class="dark:text-black"
                            v-for="bill in bills"
                            :key="bill.id"
                            :value="bill.id"
                        >
                            {{ bill.bill_number }} — Outstanding:
                            {{ formatCurrency(bill.outstanding_balance) }}
                        </option>
                    </select>
                </div>

                <!-- Amount -->
                <div>
                    <label class="block text-sm font-medium mb-1">Amount</label>
                    <input
                        type="number"
                        v-model.number="form.amount"
                        class="w-full rounded border px-3 py-2 text-sm"
                        placeholder="Enter amount"
                        min="0.01"
                        step="0.01"
                        required
                    />
                </div>

                <!-- Payment method -->
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Method</label>
                    <select
                        v-model="form.method"
                        class="w-full rounded border px-3 py-2 text-sm"
                        required
                    >
                        <option class="dark:text-black" value="cash">Cash</option>
                        <option class="dark:text-black" value="transfer">Bank Transfer</option>
                        <option class="dark:text-black" value="cheque">Cheque</option>
                        <option class="dark:text-black" value="card">Card</option>
                    </select>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <textarea
                        v-model="form.note"
                        class="w-full rounded border px-3 py-2 text-sm"
                        placeholder="Optional notes..."
                    ></textarea>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" @click="$emit('close')">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="loading">
                        {{ loading ? 'Saving...' : 'Submit Payment' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog'

const props = defineProps<{
    open: boolean
    orderId: number
}>()

const emit = defineEmits(['close', 'success'])

// Bills for dropdown
const bills = ref<any[]>([])
const form = ref({
    bill_id: '',
    amount: 0,
    method: 'cash',
    note: '',
})

const loading = ref(false)

// Load vendor bills whenever modal opens
watch(
    () => props.open,
    async (val) => {
        if (val) {
            // Fetch bills for this PO
            const response = await fetch(
                route('admin.vendor-bills.by-purchase-order', props.orderId)
            )
            bills.value = await response.json()

            // Reset form
            form.value = {
                bill_id: '',
                amount: 0,
                method: 'cash',
                note: '',
            }
        }
    }
)

async function submitPayment() {
    if (!form.value.bill_id) return

    loading.value = true
    try {
        router.post(route('admin.vendor-bill-payments.store', form.value.bill_id), {
            amount: form.value.amount,
            method: form.value.method,
            note: form.value.note,
        }, {preserveState: false, preserveScroll: false})

        emit('success')
        emit('close')
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NGN', // change to NGN if needed
        minimumFractionDigits: 2,
    }).format(value || 0)
}
</script>
