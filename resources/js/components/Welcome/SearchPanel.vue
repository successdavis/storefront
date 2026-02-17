<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

const open = ref(false)
const query = ref('')
const results = ref([])

function toggle() {
    open.value = !open.value
    if (!open.value) {
        query.value = ''
        results.value = []
    }
}

function close() {
    open.value = false
    query.value = ''
    results.value = []
}

watch(query, (value) => {
    if (!value) {
        results.value = []
        return
    }

    results.value = [
        value + ' result one',
        value + ' result two',
        value + ' result three',
    ]
})

function handleOutsideClick(e) {
    if (!e.target.closest('[data-search-root]')) {
        close()
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick)
})
</script>

<template>
    <!-- Search Button -->
    <button
        class="p-3 rounded-full"
        @click.stop="toggle"
        data-search-root
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="w-5 h-5"
        >
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
    </button>

    <!-- Overlay -->
    <div
        v-if="open"
        class="fixed inset-0 z-40 bg-black opacity-40"
    ></div>

    <!-- Desktop Panel -->
    <div
        class="hidden md:block fixed top-0 right-0 h-full w-[420px] bg-secondary z-50 transform transition-transform duration-300"
        :class="open ? 'translate-x-0' : 'translate-x-full'"
        data-search-root
    >
        <div class="flex justify-center py-3">
            <div class="w-12 h-1.5 bg-primary rounded-full"></div>
        </div>

        <div class="px-6">
            <input
                v-model="query"
                type="text"
                placeholder="Search"
                class="w-full px-4 py-3 border rounded bg-white"
            />

            <ul class="mt-4 space-y-2">
                <li
                    v-for="item in results"
                    :key="item"
                    class="p-3 bg-white rounded"
                >
                    {{ item }}
                </li>
            </ul>
        </div>
    </div>

    <!-- Mobile Panel -->
    <div
        class="md:hidden fixed inset-x-0 bottom-0 h-[75vh] bg-secondary z-50 rounded-t-2xl transform transition-transform duration-300"
        :class="open ? 'translate-y-0' : 'translate-y-full'"
        data-search-root
    >
        <div class="flex justify-center py-3">
            <div class="w-12 h-1.5 bg-primary rounded-full"></div>
        </div>

        <div class="px-6">
            <input
                v-model="query"
                type="text"
                placeholder="Search"
                class="w-full px-4 py-3 border rounded-md bg-white"
            />

            <ul class="mt-4 space-y-2 overflow-y-auto">
                <li
                    v-for="item in results"
                    :key="item"
                    class="p-3 bg-white rounded"
                >
                    {{ item }}
                </li>
            </ul>
        </div>
    </div>
</template>
