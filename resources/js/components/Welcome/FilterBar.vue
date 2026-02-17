<script setup>
import {ref} from "vue";
import FilterOptions from "@/components/Welcome/HomeFilters/FilterOptions.vue"
import Categories from "@/components/Welcome/HomeFilters/Categories.vue"
import SortBy from "@/components/Welcome/HomeFilters/SortBy.vue"
import MobileFilterBarButton from "@/components/Welcome/HomeFilters/MobileFilterBarButton.vue";

const categories = [
    { name: "iOS", url: "/ios", count: 12 },
    { name: "Android", url: "/android", count: 24 },
    { name: "Windows", url: "/windows", count: 0 },
]

const filtersOpen = ref(false)
function toggleFiltersOnMobile() {
    filtersOpen.value = !filtersOpen.value
}
</script>

<template>
    <div class="flex items-center justify-between gap-6 py-4">
        <div>
            <FilterOptions
                :isOpen="filtersOpen"
                @close="toggleFiltersOnMobile"
                @open="toggleFiltersOnMobile"
            />
        </div>

        <div class="flex-1">
            <Categories :items="categories" />
        </div>

        <div class="hidden md:block">
            <SortBy />
        </div>

        <MobileFilterBarButton
            :isOpen="filtersOpen"
            @open="toggleFiltersOnMobile"
        />
    </div>
</template>
