<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div>
            <label class="text-sm font-medium">Name</label>
            <input
                v-model="form.name"
                type="text"
                class="mt-1 block w-full rounded border px-3 py-2"
                required
            />
            <p v-if="form.errors.name" class="text-sm text-red-600">
                {{ form.errors.name }}
            </p>
        </div>

        <div>
            <label class="text-sm font-medium">Email</label>
            <input
                v-model="form.email"
                type="email"
                class="mt-1 block w-full rounded border px-3 py-2"
            />
        </div>

        <div>
            <label class="text-sm font-medium">Phone</label>
            <input
                v-model="form.phone"
                type="text"
                class="mt-1 block w-full rounded border px-3 py-2"
            />
        </div>

        <div>
            <label class="text-sm font-medium">Address</label>
            <textarea
                v-model="form.address"
                rows="2"
                class="mt-1 block w-full rounded border px-3 py-2"
            ></textarea>
        </div>

        <div class="flex justify-end gap-3">
            <button
                v-if="showCancel"
                type="button"
                @click="$emit('cancel')"
                class="rounded border px-4 py-2"
            >
                Cancel
            </button>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800 disabled:opacity-50"
            >
                Save
            </button>
        </div>
    </form>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
/**
 * Props
 *  - onSuccess: callback when vendor created (receives new vendor object)
 *  - showCancel: bool to display a cancel button
 */
const props = defineProps({
    onSuccess: { type: Function, required: false },
    showCancel: { type: Boolean, default: false },
});

const emit = defineEmits(['cancel', 'created']);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
});


async function submit() {
    try {
        const { data } = await axios.post('/admin/vendors/store', form.data());
        const newVendor = data.vendor;
        emit('created', newVendor);
        // form.reset();
    } catch (error) {
        if (error.response?.status === 422) {
            form.setError(error.response.data.errors);
        }
    }
}
</script>
