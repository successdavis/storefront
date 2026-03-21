<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { Monitor, Hash, MapPin } from "lucide-vue-next";

const props = defineProps({
    terminals: {
        type: Array,
        required: true,
    },
    pos_routes: {
        type: Object,
        required: true,
    },
    use_pos_terminal_password: {
        type: Boolean,
        required: true,
    }
});

const form = useForm({
    terminal_id: "",
    supervisor_password: "",
});

const canSubmit = computed(() => form.terminal_id !== "");
const selectedTerminal = computed(() =>
    props.terminals.find(t => t.id === form.terminal_id)
);

const submit = () => {
    form.post(props.pos_routes.set_terminal);
};
</script>

<template>
    <Head title="Select POS Terminal" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 p-6">

        <!-- Card -->
        <div class="bg-white dark:bg-gray-800 shadow-xl rounded-xl p-8 w-full max-w-xl border dark:border-gray-700">

            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 flex items-center gap-2">
                <Monitor class="w-6 h-6" />
                Select POS Terminal
            </h1>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Choose a terminal to continue processing sales.
            </p>

            <!-- Terminal list -->
            <div class="space-y-3 max-h-64 overflow-y-auto pr-2 scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-700">
                <label
                    v-for="terminal in terminals"
                    :key="terminal.id"
                    class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer transition-all duration-150
                          dark:border-gray-700 bg-gray-50 dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-gray-600"
                >
                    <input
                        type="radio"
                        v-model="form.terminal_id"
                        :value="terminal.id"
                        class="w-5 h-5 text-blue-600 focus:ring-blue-500"
                    />

                    <div class="flex flex-col">
                        <span class="font-semibold text-gray-700 dark:text-gray-200">
                            {{ terminal.name }}
                        </span>

                        <span class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                            <MapPin class="w-4 h-4 mr-1" />
                            {{ terminal.location ?? "No location set" }}
                        </span>
                    </div>

                    <Hash class="ml-auto w-4 h-4 text-gray-400" />
                </label>
            </div>

            <div class="mt-4" v-if="use_pos_terminal_password">
                <div>
                    <label for="first_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Supervisor Password</label>
                    <input v-model="form.supervisor_password" type="password" id="first_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Enter Password" required />
                </div>
            </div>

            <!-- Submit button -->
            <button
                @click="submit"
                :disabled="!canSubmit"
                class="mt-6 w-full rounded-lg py-3 font-bold transition-colors
                       border border-transparent
                       bg-blue-600 hover:bg-blue-700 text-white
                       disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
                Use Selected Terminal
            </button>

            <!-- Footer text -->
            <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-4">
                You will be locked to this terminal until logout.
            </p>

        </div>
    </div>
</template>

<style>
/* Small scrollbar styling */
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    border-radius: 6px;
}
</style>
