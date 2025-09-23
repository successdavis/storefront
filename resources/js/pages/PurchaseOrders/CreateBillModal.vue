<template>
    <Modal :open="open" @close="onClose">
        <template #title>Create Vendor Bill</template>

        <form @submit.prevent="submit">
            <div class="mb-4">
                <label class="block text-sm font-medium">Bill Date</label>
                <input v-model="form.bill_date" type="date" class="mt-1 w-full rounded border px-2 py-1" />
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium">Amount</label>
                <input v-model.number="form.total_amount" type="number" min="0" class="mt-1 w-full rounded border px-2 py-1" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Create Bill' }}
            </Button>
        </form>
    </Modal>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Modal from '@/components/ui/modal';
import { Button } from '@/components/ui/button';

interface Props {
    open: boolean;
    orderId: number;
}
const props = defineProps<Props>();
const emit = defineEmits(['close','success']);

const form = useForm({
    bill_date: new Date().toISOString().substring(0,10),
    total_amount: 0
});

function submit() {
    form.post(route('admin.vendor-bills.store', { purchase_order: props.orderId }), {
        onSuccess: () => {
            emit('success');
            emit('close');
        }
    });
}

function onClose() {
    emit('close');
}
</script>
