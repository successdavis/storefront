<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    link: {
        type: Object,
        required: true
    },
    open: {
        type: Boolean,
        required: true
    }
})

const emit = defineEmits(['toggle'])

function toggle() {
    emit('toggle')
}
</script>


<template>
    <li class="relative group rounded">
        <!-- Parent Link/Button -->
        <div class="relative">
            <Link
                v-if="!link.children"
                :href="link.url"
                class="inline-block px-4 py-2 text-primary hover:text-secondary hover:bg-primary rounded transition-colors duration-200"
            >
                <span class="relative">
                    {{ link.text }}
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary group-hover:w-full transition-all duration-200 ease-out"></span>
                </span>
            </Link>

            <button
                v-else
                type="button"
                class="flex items-center gap-1 px-4 py-2 text-gray-700 hover:text-primary hover:bg-primary hover:text-secondary rounded transition-colors duration-200"
                @click="toggle"
            >
                <span class="relative">
                    {{ link.text }}
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary group-hover:w-full transition-all duration-200 ease-out"></span>
                </span>

                <svg
                    class="w-4 h-4 transition-transform duration-300 ease-out"
                    :class="open ? 'rotate-180' : 'rotate-0'"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        </div>

        <!-- Dropdown Children -->
        <div
            v-if="link.children"
            class="absolute left-0 top-full mt-1 min-w-[200px] overflow-hidden z-50"
        >
            <transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 transform -translate-y-2"
                enter-to-class="opacity-100 transform translate-y-0"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 transform translate-y-0"
                leave-to-class="opacity-0 transform -translate-y-2"
            >
                <ul
                    v-show="open"
                    class="bg-white border border-gray-200 rounded-lg shadow-lg py-1"
                >
                    <li
                        v-for="child in link.children"
                        :key="child.text"
                        class="group/child"
                    >
                        <Link
                            :href="child.url"
                            class="block px-4 py-2.5 text-gray-700 hover:text-black hover:bg-gray-50 transition-colors duration-150"
                        >
                            <span class="relative">
                                {{ child.text }}
                                <span class="absolute bottom-1 left-4 right-4 h-0.5 bg-black scale-x-0 group-hover/child:scale-x-100 transition-transform duration-200 ease-out origin-left"></span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </transition>
        </div>
    </li>
</template>
