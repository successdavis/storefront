<script setup>
import { usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const page = usePage();
const show = ref(false);
const message = ref("");
const type = ref("success");

watch(
    () => page.props.flash,
    (flash) => {
        const key = Object.keys(flash).find(k => flash[k]);

        if (!key) return;

        type.value = key;
        message.value = flash[key];
        show.value = true;

        setTimeout(() => show.value = false, 4000);
    },
    { deep: true }
);
</script>

<template>
    <transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-x-4"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-x-0"
        leave-to-class="opacity-0 translate-x-4"
    >
        <div v-if="show"
             class="fixed top-4 right-4 z-[9999] min-w-[260px] px-4 py-3 rounded-lg shadow-lg text-white font-medium"
             :class="{
                'bg-green-600': type === 'success',
                'bg-red-600': type === 'error',
                'bg-yellow-500': type === 'warning',
                'bg-blue-600': type === 'info',
             }"
        >
            {{ message }}
        </div>
    </transition>
</template>
