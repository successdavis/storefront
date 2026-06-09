<script setup>
import { usePage } from "@inertiajs/vue3";
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { eventBus } from "@/eventBus.js";

const page = usePage();
const show = ref(false);
const message = ref("");
const type = ref("success");
let hideTimer = null;

const showToast = (payload) => {
    const nextType = payload?.type || "success";
    const nextMessage = payload?.message;

    if (!nextMessage) return;

    type.value = nextType;
    message.value = nextMessage;
    show.value = true;

    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    hideTimer = setTimeout(() => show.value = false, 4000);
};

watch(
    () => page.props.flash,
    (flash) => {
        const key = ['success', 'error', 'warning', 'info'].find(k => flash?.[k]);

        if (!key) return;

        showToast({ type: key, message: flash[key] });
    },
    { deep: true, immediate: true }
);

onMounted(() => {
    eventBus.on('toast', showToast);
});

onBeforeUnmount(() => {
    eventBus.off('toast', showToast);
});
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
