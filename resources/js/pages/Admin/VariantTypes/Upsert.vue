<script setup>
import { onMounted, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
  variantType: {
    type: Object,
    default: null,
  },
  values: {
    type: Array,
    default: () => [],
  },
});

// one page for both create and edit
const isEdit = !!props.variantType;

const form = useForm({
  name: props.variantType?.name ?? '',
  values: props.values.length ? props.values.map(v => ({ id: v.id ?? null, value: v.value ?? '' })) : [
    { id: null, value: '' },
  ],
});

function addRow() {
  form.values.push({ id: null, value: '' });
}

function removeRow(index) {
  // If it is an existing record, just drop it from payload.
  form.values.splice(index, 1);
  if (form.values.length === 0) {
    form.values.push({ id: null, value: '' });
  }
}

function submit() {
  if (isEdit) {
    form.put(`/admin/variant-types/${props.variantType.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        // stay on page and keep data fresh
      },
    });
  } else {
    form.post('/admin/variant-types', {
      preserveScroll: true,
      onSuccess: () => {
        // stay on page and reset to blank
        form.reset('name', 'values');
        form.name = '';
        form.values = [{ id: null, value: '' }];
      },
    });
  }
}

onMounted(() => {
  // focus first input for usability
  const el = document.getElementById('name');
  if (el) el.focus();
});
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">
        {{ isEdit ? 'Edit Variant Type' : 'Create Variant Type' }}
      </h1>
      <Link href="/variant-types" class="rounded border px-3 py-2 text-sm hover:bg-gray-50">Back</Link>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          class="mt-1 w-full rounded border px-3 py-2"
          placeholder="e.g. Color, Size, Material"
        />
        <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
      </div>

      <div>
        <div class="mb-2 flex items-center justify-between">
          <label class="text-sm font-medium">Values</label>
          <button type="button" class="rounded border px-2 py-1 text-sm hover:bg-gray-50" @click="addRow">
            Add value
          </button>
        </div>

        <div class="space-y-2">
          <div
            v-for="(row, index) in form.values"
            :key="index"
            class="flex items-center gap-2"
          >
            <input
              v-model="row.value"
              type="text"
              class="w-full rounded border px-3 py-2"
              placeholder="e.g. Red, Blue, Large"
            />
            <button
              type="button"
              class="rounded border px-2 py-1 text-sm hover:bg-red-50"
              @click="removeRow(index)"
            >
              Remove
            </button>
          </div>
        </div>

        <div v-if="form.errors['values']" class="mt-1 text-sm text-red-600">{{ form.errors['values'] }}</div>
        <div v-for="(err, i) in Object.keys(form.errors).filter(k => k.startsWith('values.') )" :key="i" class="mt-1 text-sm text-red-600">
          {{ form.errors[Object.keys(form.errors).filter(k => k.startsWith('values.'))[i]] }}
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button
          type="submit"
          :disabled="form.processing"
          class="rounded bg-gray-900 px-4 py-2 text-white hover:opacity-90 disabled:opacity-50"
        >
          {{ isEdit ? 'Save changes' : 'Create' }}
        </button>
        <button
          type="button"
          class="rounded border px-4 py-2 hover:bg-gray-50"
          @click="isEdit ? $inertia.reload({ only: ['variantType','values'] }) : form.reset('name','values')"
        >
          {{ isEdit ? 'Reset' : 'Clear' }}
        </button>
      </div>
    </form>
  </div>
</template>
