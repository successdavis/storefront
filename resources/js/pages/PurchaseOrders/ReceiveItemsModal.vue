<template>
    <Modal :open="open" @close="onClose">
        <template #title>Receive Items</template>

        <form @submit.prevent="submit">
            <div v-for="item in order.items" :key="item.id" class="mb-4">
                <label class="block text-sm font-medium">
                    {{ item.product_variant?.title ?? '—' }}
                </label>
                <input
                    v-model.number="form.items[item.id]"
                    type="number"
                    :max="item.remaining_quantity"
                    min="0"
                    class="mt-1 w-full rounded border px-2 py-1"
                />
                <p class="text-xs text-muted-foreground">
                    Remaining: {{ item.remaining_quantity }}
                </p>
            </div>

            <Button type="submit" class="w-full mt-4" :disabled="form.processing">
                {{ form.processing ? 'Receiving…' : 'Submit Receipt' }}
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
    order: any;
}
const props = defineProps<Props>();
const emit = defineEmits(['close','success']);

const form = useForm({
    items: {} as Record<number, number>
});

function submit() {
    form.post(route('admin.item-receipts.store', { purchase_order: props.order.id }), {
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
