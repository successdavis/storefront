<script setup>
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import {
    ArrowRight,
    Box,
    Clock3,
    FolderSearch,
    LoaderCircle,
    Search,
    Sparkles,
    Store,
    X,
} from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    initialQuery: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search products, brands, categories',
    },
})

const RECENT_SEARCHES_KEY = 'storefront.recent_searches'
const rootRef = ref(null)
const searchInputRef = ref(null)
const isOpen = ref(false)
const isLoading = ref(false)
const highlightedIndex = ref(-1)
const query = ref(props.initialQuery)
const groupedSuggestions = ref([])
const recentSearches = ref(loadRecentSearches())

let debounceTimer = null

watch(
    () => props.initialQuery,
    (value) => {
        if (!isOpen.value) {
            query.value = value || ''
        }
    }
)

watch(query, (value) => {
    if (!isOpen.value) {
        return
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer)
    }

    const term = value.trim()
    if (term.length < 2) {
        groupedSuggestions.value = []
        isLoading.value = false
        highlightedIndex.value = -1
        return
    }

    isLoading.value = true
    debounceTimer = setTimeout(() => fetchSuggestions(term), 220)
})

watch(highlightedIndex, async (value) => {
    await nextTick()

    if (value < 0 || !rootRef.value) {
        return
    }

    const activeOption = rootRef.value.querySelector(`[data-option-index="${value}"]`)
    activeOption?.scrollIntoView({ block: 'nearest' })
})

const visibleGroups = computed(() => {
    if (query.value.trim().length >= 2) {
        return groupedSuggestions.value
    }

    if (!recentSearches.value.length) {
        return []
    }

    return [
        {
            key: 'recent',
            label: 'Recent Searches',
            items: recentSearches.value.map((term) => ({
                id: `recent:${term}`,
                type: 'recent',
                label: term,
                meta: 'Search again',
                href: route('store.search', { q: term }),
            })),
        },
    ]
})

const flatItems = computed(() => visibleGroups.value.flatMap((group) => group.items))

function openPanel() {
    isOpen.value = true
    highlightedIndex.value = flatItems.value.length ? 0 : -1

    if (query.value.trim().length >= 2) {
        fetchSuggestions(query.value.trim())
    }
}

function closePanel() {
    isOpen.value = false
    isLoading.value = false
    highlightedIndex.value = -1
}

async function fetchSuggestions(term) {
    try {
        const { data } = await axios.get(route('store.search.suggestions'), {
            params: { q: term },
        })

        if (query.value.trim() !== term) {
            return
        }

        groupedSuggestions.value = Array.isArray(data?.groups) ? data.groups : []
        highlightedIndex.value = flatItems.value.length ? 0 : -1
    } catch (error) {
        groupedSuggestions.value = []
        highlightedIndex.value = -1
    } finally {
        if (query.value.trim() === term) {
            isLoading.value = false
        }
    }
}

function submitSearch() {
    const term = query.value.trim()
    if (term !== '') {
        saveRecentSearch(term)
    }

    closePanel()
    router.get(route('store.search'), {
        q: term || undefined,
    })
}

function activateItem(item) {
    const term = query.value.trim()

    if (term !== '') {
        saveRecentSearch(term)
    }

    closePanel()
    router.visit(item.href)
}

function clearQuery() {
    query.value = ''
    groupedSuggestions.value = []
    highlightedIndex.value = flatItems.value.length ? 0 : -1
    nextTick(() => searchInputRef.value?.focus())
}

function handleKeydown(event) {
    if (!isOpen.value && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
        openPanel()
        return
    }

    if (!isOpen.value) {
        if (event.key === 'Enter') {
            event.preventDefault()
            submitSearch()
        }
        return
    }

    if (event.key === 'Escape') {
        event.preventDefault()
        closePanel()
        return
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault()

        if (!flatItems.value.length) {
            return
        }

        highlightedIndex.value = highlightedIndex.value >= flatItems.value.length - 1
            ? 0
            : highlightedIndex.value + 1
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()

        if (!flatItems.value.length) {
            return
        }

        highlightedIndex.value = highlightedIndex.value <= 0
            ? flatItems.value.length - 1
            : highlightedIndex.value - 1
    }

    if (event.key === 'Enter') {
        event.preventDefault()
        const activeItem = flatItems.value[highlightedIndex.value]
        if (activeItem) {
            activateItem(activeItem)
            return
        }

        submitSearch()
    }
}

function handleClickOutside(event) {
    if (!rootRef.value?.contains(event.target)) {
        closePanel()
    }
}

function highlightParts(text, term) {
    if (!term) {
        return [{ text, match: false }]
    }

    const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const regex = new RegExp(`(${escaped})`, 'ig')
    const pieces = text.split(regex).filter(Boolean)

    return pieces.map((piece) => ({
        text: piece,
        match: piece.toLowerCase() === term.toLowerCase(),
    }))
}

function groupIcon(groupKey) {
    return {
        queries: Sparkles,
        products: Box,
        categories: FolderSearch,
        brands: Store,
        recent: Clock3,
    }[groupKey] || Search
}

function loadRecentSearches() {
    if (typeof window === 'undefined') {
        return []
    }

    try {
        const parsed = JSON.parse(window.localStorage.getItem(RECENT_SEARCHES_KEY) || '[]')
        return Array.isArray(parsed) ? parsed.filter(Boolean).slice(0, 6) : []
    } catch (error) {
        return []
    }
}

function saveRecentSearch(term) {
    if (typeof window === 'undefined') {
        return
    }

    const next = [term, ...recentSearches.value.filter((item) => item !== term)].slice(0, 6)
    recentSearches.value = next
    window.localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next))
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside)
})

onBeforeUnmount(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer)
    }

    document.removeEventListener('mousedown', handleClickOutside)
})
</script>

<template>
    <div ref="rootRef" class="relative z-50 flex-1">
        <form class="relative flex items-center gap-2" @submit.prevent="submitSearch">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    ref="searchInputRef"
                    v-model="query"
                    type="search"
                    :placeholder="placeholder"
                    class="h-11 w-full rounded-2xl border border-amber-200 bg-white px-11 pr-10 text-sm text-slate-700 shadow-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-amber-400 dark:focus:ring-amber-400/20"
                    autocomplete="off"
                    @focus="openPanel"
                    @keydown="handleKeydown"
                >
                <button
                    v-if="query"
                    type="button"
                    class="absolute right-3 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    @click="clearQuery"
                >
                    <X class="size-4" />
                </button>
            </div>

            <button
                type="submit"
                class="inline-flex h-11 items-center gap-2 rounded-2xl bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-700 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
            >
                <span class="hidden sm:inline">Search</span>
                <ArrowRight class="size-4" />
            </button>
        </form>

        <div
            v-if="isOpen"
            class="absolute left-0 right-0 top-[calc(100%+0.75rem)] z-40 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-950 dark:shadow-black/30"
        >
            <div
                v-if="isLoading"
                class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                <LoaderCircle class="size-4 animate-spin" />
                Fetching suggestions...
            </div>

            <div v-else-if="!visibleGroups.length" class="px-4 py-8 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    <Search class="size-5" />
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    Start typing to search the catalog
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Products, categories, brands, and helpful suggestions will appear here.
                </p>
            </div>

            <div v-else class="max-h-[28rem] overflow-y-auto">
                <section
                    v-for="group in visibleGroups"
                    :key="group.key"
                    class="border-b border-slate-100 last:border-b-0 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2 px-4 pb-2 pt-3 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">
                        <component :is="groupIcon(group.key)" class="size-3.5" />
                        {{ group.label }}
                    </div>

                    <button
                        v-for="item in group.items"
                        :key="item.id"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition"
                        :class="highlightedIndex === flatItems.findIndex((candidate) => candidate.id === item.id)
                            ? 'bg-amber-50 dark:bg-amber-500/10'
                            : 'hover:bg-slate-50 dark:hover:bg-slate-900'"
                        :data-option-index="flatItems.findIndex((candidate) => candidate.id === item.id)"
                        @mouseenter="highlightedIndex = flatItems.findIndex((candidate) => candidate.id === item.id)"
                        @click="activateItem(item)"
                    >
                        <img
                            v-if="item.image"
                            :src="item.image"
                            :alt="item.label"
                            class="size-12 rounded-2xl border border-slate-200 object-cover dark:border-slate-800"
                        >
                        <div
                            v-else
                            class="flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-900"
                        >
                            <component :is="groupIcon(group.key)" class="size-4" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                <template v-for="(part, index) in highlightParts(item.label, query.trim())" :key="`${item.id}-${index}`">
                                    <mark
                                        v-if="part.match"
                                        class="rounded bg-amber-200/70 px-0.5 text-slate-900 dark:bg-amber-400/30 dark:text-amber-100"
                                    >
                                        {{ part.text }}
                                    </mark>
                                    <span v-else>{{ part.text }}</span>
                                </template>
                            </p>
                            <p v-if="item.meta" class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ item.meta }}
                            </p>
                        </div>

                        <ArrowRight class="size-4 text-slate-300 dark:text-slate-600" />
                    </button>
                </section>
            </div>
        </div>
    </div>
</template>
