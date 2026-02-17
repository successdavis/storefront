<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    navLinks: {
        type: Array,
        required: true,
    },
})

const open = ref(false)
const stack = ref([props.navLinks])

function toggle() {
    open.value = !open.value
    stack.value = [props.navLinks]
}

function openChildren(children) {
    stack.value.push(children)
}

function goBack() {
    if (stack.value.length > 1) {
        stack.value.pop()
    }
}

const currentList = () => stack.value[stack.value.length - 1]
</script>

<template>
    <!-- Hamburger -->
    <button
        class="md:hidden  right-6 z-50 text-primary p-3 rounded-full"
        @click="toggle"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="w-6 h-6"
        >
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
    </button>

    <!-- Overlay -->
    <div
        v-if="open"
        class="fixed inset-0 z-40 bg-black opacity-40"
        @click="toggle"
    ></div>

    <!-- Drawer -->
    <div
        class="fixed inset-x-0 bottom-0 z-50 bg-secondary rounded-t-2xl transform transition-transform duration-300"
        :class="open ? 'translate-y-0' : 'translate-y-full'"
        style="height: 80vh"
    >
        <!-- Drag handle -->
        <div class="flex justify-center py-3">
            <div class="w-12 h-1.5 bg-primary rounded-full"></div>
        </div>

        <!-- Header -->
        <div class="px-6 pb-4 flex items-center gap-4">
            <button
                v-if="stack.length > 1"
                class="text-primary"
                @click="goBack"
            >
                ←
            </button>

            <span class="text-primary font-bold text-lg">
                Menu
            </span>
        </div>

        <!-- List -->
        <ul class="px-6 space-y-2 overflow-y-auto h-full pb-10">
            <li
                v-for="item in currentList()"
                :key="item.text"
                class="border-b"
            >
                <div
                    v-if="item.children"
                    class="flex items-center justify-between py-4 text-primary"
                    @click="openChildren(item.children)"
                >
                    <span>{{ item.text }}</span>

                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 01.02-1.06L10.94 10 7.23 6.29a.75.75 0 111.06-1.06l4.25 4.24a.75.75 0 010 1.06l-4.25 4.24a.75.75 0 01-1.08-.02z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>

                <Link
                    v-else
                    :href="item.url"
                    class="block py-4 text-primary"
                    @click="toggle"
                >
                    {{ item.text }}
                </Link>
            </li>
        </ul>
    </div>
</template>
