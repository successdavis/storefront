<template>
  <transition name="fade ">
    <div
      v-if="visible"
      :class="['p-4 rounded shadow-lg fixed top-5 right-5 z-50', typeClass]"
    >
      {{ message }}
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'

const props = defineProps({
  type: { type: String, default: 'success' },
  message: String,
})

const emit = defineEmits(['hide'])

const visible = ref(true)

const typeClass = computed(() => {
  return {
    success: 'bg-green-500 text-white',
    error: 'bg-red-500 text-white',
    warning: 'bg-yellow-400 text-black',
  }[props.type] || 'bg-blue-500 text-white'
})

onMounted(() => {
  setTimeout(() => {
    visible.value = false
    emit('hide')
  }, 3000)
})
</script>

<style scoped>
.fade-enter-from {
  opacity: 0;
  transform: translateY(-20px); /* Start 20px above */
}
.fade-enter-to {
  opacity: 1;
  transform: translateY(0); /* End at normal position */
}

.fade-leave-from {
  opacity: 1;
  transform: translateY(0);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px); /* Float up slightly */
}

.fade-enter-active,
.fade-leave-active {
  transition: all 0.5s ease;
}
</style>
