<script setup>
import { CheckCircle, Clock, XCircle } from 'lucide-vue-next'

defineProps({ status: String })
</script>

<template>
  <span
      class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
      :class="{
      'bg-green-100 text-green-700': status === 'Completed',
      'bg-blue-100 text-blue-700': status === 'Processing',
      'bg-red-100 text-red-700': status === 'Failed',
    }"
  >
    <CheckCircle v-if="status === 'Completed'" class="w-3 h-3" />
    <Clock v-else-if="status === 'Processing'" class="w-3 h-3" />
    <XCircle v-else class="w-3 h-3" />
    {{ status }}
  </span>
</template>
