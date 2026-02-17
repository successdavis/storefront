<template>
    <div class="tech-specs-container">
        <div class="tech-specs-header mb-6">
            <h2 class="text-2xl lg:text-3xl font-bold text-gray-900">Technical Specifications</h2>
            <p class="text-gray-600 text-sm mt-2">Complete details about this product</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Accordion Sections (Takes 2 columns on desktop) -->
            <div class="lg:col-span-2 space-y-4">
                <SpecAccordionItem
                    v-for="(section, index) in specSections"
                    :key="section.id"
                    :title="section.title"
                    :items="section.items"
                    :is-open="openSection === section.id"
                    @toggle="toggleSection(section.id)"
                />
            </div>

            <!-- Included in Box (Sidebar on desktop, full width on mobile) -->
            <div class="lg:col-span-1">
                <IncludedInBox :items="includedItems" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import SpecAccordionItem from './TechSpecsComps/SpecAccordionItem.vue'
import IncludedInBox from './TechSpecsComps/IncludedInBox.vue'

const openSection = ref('basic-details') // Default open section

const specSections = ref([
    {
        id: 'basic-details',
        title: 'Basic Details',
        items: [
            { label: 'Brand', value: 'HP' },
            { label: 'Model', value: 'EliteBook 845 G7' },
            { label: 'Type', value: 'Ultrabook' },
            { label: 'Processor', value: 'AMD Ryzen 5 Pro with Radeon Graphics 2.10GHz' },
            { label: 'Storage capacity', value: '256GB SSD' },
            { label: 'Maximum Storage capacity', value: '2TB SSD' },
            { label: 'RAM', value: '16GB' },
            { label: 'Maximum RAM upgrade', value: '64GB' },
            { label: 'Supported OS', value: 'Windows 11' },
            { label: 'Battery health', value: 'Excellent' },
            { label: 'Minimum battery backup', value: '2 hrs' },
            { label: 'Maximum battery backup', value: 'Depends on usage' },
        ]
    },
    {
        id: 'display-properties',
        title: 'Display Properties',
        items: [
            { label: 'Screen size', value: '14-inch' },
            { label: 'Resolution', value: '1920 x 1080' },
            { label: 'Graphics type', value: 'AMD Radeon' },
            { label: 'Total available graphics memory', value: '8GB' },
            { label: 'Dedicated video memory', value: '512MB' },
            { label: 'Shared system memory', value: '7GB' },
        ]
    },
    {
        id: 'special-features',
        title: 'Special Features',
        items: [
            { label: 'HDMI', value: 'Yes' },
            { label: 'Webcam', value: 'Yes' },
            { label: 'WiFi', value: 'Yes' },
            { label: 'Keypad light', value: 'Yes' },
            { label: 'Touchscreen', value: 'No' },
            { label: 'Optical drive', value: 'No' },
            { label: 'USB Type-A ports', value: '2' },
            { label: 'USB Type-C ports', value: '2' },
            { label: 'VGA output', value: 'No' },
            { label: 'SD card reader', value: 'No' },
        ]
    }
])

const includedItems = ref([
    { name: 'Laptop', icon: 'laptop' },
    { name: 'Charger', icon: 'charger' },
    { name: 'Bag', icon: 'bag' }
])

const toggleSection = (sectionId) => {
    // If clicking the same section, close it; otherwise open the new one
    openSection.value = openSection.value === sectionId ? null : sectionId
}
</script>

<style scoped>
.tech-specs-container {
    padding: 2rem 0;
}

@media (max-width: 1024px) {
    .tech-specs-container {
        padding: 1.5rem 0;
    }
}
</style>
