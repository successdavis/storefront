<script setup>
import {ref, computed, onMounted} from 'vue'
import SpecificationItem from './SpecificationItem.vue'

const props = defineProps({
    specifications: {
        type: Array,
        default: () => ({})
    }
})

const currentView = ref('right') // 'left' or 'right'

// Split specs into two groups

const iconMap = {
    cpu: '⚡',
    ram: '🧠',
    storage: '💾',
    display: '📱'
}

const specs = computed(() => {
    if (!Array.isArray(props.specifications)) {
        return []
    }

    return props.specifications.map(spec => {
        return {
            icon: iconMap[spec.icon] || '❓',
            label: spec.label,
            value: spec.value
        }
    })
})


const leftSpecs = computed(() => {
    // First two specs (Display, Processor)
    return specs.value.slice(0, 2)
})

const rightSpecs = computed(() => {
    // Last two specs (RAM, Storage)
    return specs.value.slice(2, 4)
})

const hasLeftSpecs = computed(() => leftSpecs.value.length > 0)
const hasRightSpecs = computed(() => rightSpecs.value.length > 0)

const handleMouseMove = (event) => {
    const rect = event.currentTarget.getBoundingClientRect()
    const x = event.clientX - rect.left
    const width = rect.width
    const percentage = x / width

    // If mouse is on left half, show left specs (Display, Processor)
    // If mouse is on right half, show right specs (RAM, Storage)
    if (percentage < 0.5) {
        currentView.value = 'right'
    } else {
        currentView.value = 'left'
    }
}

const handleMouseLeave = () => {
    // Reset to right view (Display, Processor) when mouse leaves
    currentView.value = 'right'
}

const displayedSpecs = computed(() => {
    if (currentView.value === 'left') {
        return rightSpecs.value // Show RAM, Storage
    } else {
        return leftSpecs.value // Show Display, Processor (default)
    }
})
</script>

<template>
    <div
        class="w-full h-10 rounded-lg p-1 overflow-hidden relative cursor-pointer"
        @mousemove="handleMouseMove"
        @mouseleave="handleMouseLeave"
    >
        <div class="flex flex-col gap-2 h-full">
            <TransitionGroup
                name="spec-slide"
                tag="div"
                class="flex gap-2 flex-1"
            >
                <SpecificationItem
                    v-for="spec in displayedSpecs"
                    :key="spec.label"
                    :icon="spec.icon"
                    :label="spec.label"
                    :value="spec.value"
                />
            </TransitionGroup>
        </div>

        <!-- Indicator dots -->
        <div class="absolute bottom-2 right-2 flex gap-1">
            <div
                v-if="hasLeftSpecs"
                :class="[
          'w-1.5 h-1.5 rounded-full transition-all duration-300',
          currentView === 'right' ? 'bg-blue-500' : 'bg-gray-300'
        ]"
            ></div>
            <div
                v-if="hasRightSpecs"
                :class="[
          'w-1.5 h-1.5 rounded-full transition-all duration-300',
          currentView === 'left' ? 'bg-blue-500' : 'bg-gray-300'
        ]"
            ></div>
        </div>
    </div>
</template>

<style scoped>
.spec-slide-enter-active,
.spec-slide-leave-active {
    transition: all 0.3s ease;
}

.spec-slide-enter-from {
    opacity: 0;
    transform: translateX(20px);
}

.spec-slide-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}

.spec-slide-move {
    transition: transform 0.3s ease;
}
</style>
