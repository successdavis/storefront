<template>
    <div class="flex items-center border border-secondary rounded-md overflow-hidden">
        <input
            v-model="email"
            type="email"
            placeholder="Enter your email"
            class="w-full bg-transparent px-4 py-3 outline-none text-secondary placeholder:text-secondary"
        />
        <button
            @click="subscribe"
            class="px-4 flex items-center justify-center"
        >
            <!-- Send Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                <path d="M2 21l21-9L2 3v7l15 2-15 2z"/>
            </svg>
        </button>
    </div>
</template>

<script setup>


import {ref} from "vue";

const email = ref('')

const subscribe = () => {
    if (!email.value) return
    alert('Subscribed with: '+ email.value)
    email.value = ''
}
</script>

<style scoped>

</style>
