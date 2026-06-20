<script setup>
import { Check, ChevronDown, Plus, Search } from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { default: '' },
    options: { type: Array, default: () => [] },
    id: { type: String, default: '' },
    placeholder: { type: String, default: 'Search or select' },
    emptyLabel: { type: String, default: 'No matching records found.' },
    valueKey: { type: String, default: 'id' },
    labelKey: { type: String, default: 'label' },
    metaKey: { type: String, default: '' },
    metaPrefix: { type: String, default: '' },
    searchKeys: { type: Array, default: () => [] },
    actionLabel: { type: String, default: '' },
    dropdownPosition: { type: String, default: 'absolute' },
    dropdownWidth: { type: String, default: 'trigger' },
    portal: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'select', 'action'])

const rootRef = ref(null)
const inputRef = ref(null)
const dropdownRef = ref(null)
const query = ref('')
const isOpen = ref(false)
const highlightedIndex = ref(-1)
const lastSelectedLabel = ref('')
const dropdownStyle = ref({})
const generatedId = `searchable-select-${Math.random().toString(36).slice(2)}`

const inputId = computed(() => props.id || generatedId)
const listboxId = computed(() => `${inputId.value}-options`)
const actionOffset = computed(() => (props.actionLabel ? 1 : 0))

const selectedOption = computed(() => {
    if (isEmptyValue(props.modelValue)) {
        return null
    }

    return props.options.find((option) => sameValue(optionValue(option), props.modelValue)) ?? null
})

const filteredOptions = computed(() => {
    const term = query.value.trim().toLowerCase()

    if (!term || term === lastSelectedLabel.value.toLowerCase()) {
        return props.options
    }

    return props.options.filter((option) => optionSearchText(option).includes(term))
})

const totalChoices = computed(() => actionOffset.value + filteredOptions.value.length)

const activeDescendant = computed(() => {
    if (!isOpen.value || highlightedIndex.value < 0) {
        return undefined
    }

    if (props.actionLabel && highlightedIndex.value === 0) {
        return `${inputId.value}-action`
    }

    const option = filteredOptions.value[highlightedIndex.value - actionOffset.value]
    return option ? optionId(option) : undefined
})

watch(selectedOption, (option) => {
    if (option) {
        const label = optionLabel(option)
        query.value = label
        lastSelectedLabel.value = label
        return
    }

    if (isEmptyValue(props.modelValue) && !isOpen.value && query.value === lastSelectedLabel.value) {
        query.value = ''
        lastSelectedLabel.value = ''
    }
}, { immediate: true })

watch(filteredOptions, () => {
    if (!isOpen.value) {
        return
    }

    highlightedIndex.value = totalChoices.value ? 0 : -1
})

watch(highlightedIndex, scrollHighlightedIntoView)

function isEmptyValue(value) {
    return value === null || value === undefined || value === ''
}

function sameValue(left, right) {
    return !isEmptyValue(left) && !isEmptyValue(right) && String(left) === String(right)
}

function optionValue(option) {
    return option?.[props.valueKey]
}

function optionLabel(option) {
    return String(option?.[props.labelKey] ?? '')
}

function optionMeta(option) {
    if (!props.metaKey || option?.[props.metaKey] === null || option?.[props.metaKey] === undefined || option?.[props.metaKey] === '') {
        return ''
    }

    return `${props.metaPrefix}${option[props.metaKey]}`
}

function optionSearchText(option) {
    const keys = props.searchKeys.length
        ? props.searchKeys
        : [props.labelKey, props.metaKey].filter(Boolean)

    return keys
        .map((key) => option?.[key])
        .filter((value) => value !== null && value !== undefined)
        .join(' ')
        .toLowerCase()
}

function optionId(option) {
    return `${inputId.value}-option-${optionValue(option)}`
}

function isSelected(option) {
    return sameValue(optionValue(option), props.modelValue)
}

function openDropdown() {
    updateDropdownPosition()
    isOpen.value = true

    const selectedIndex = filteredOptions.value.findIndex(isSelected)
    highlightedIndex.value = selectedIndex >= 0
        ? selectedIndex + actionOffset.value
        : (totalChoices.value ? 0 : -1)
}

function closeDropdown() {
    isOpen.value = false
    highlightedIndex.value = -1
}

function toggleDropdown() {
    if (isOpen.value) {
        closeDropdown()
        return
    }

    openDropdown()
    nextTick(() => inputRef.value?.focus())
}

function handleInput() {
    isOpen.value = true

    if (selectedOption.value && query.value !== optionLabel(selectedOption.value)) {
        emit('update:modelValue', '')
    }
}

function handleKeydown(event) {
    if (!isOpen.value && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault()
        openDropdown()
        return
    }

    if (event.key === 'Escape') {
        closeDropdown()
        return
    }

    if (!isOpen.value) {
        return
    }

    if (event.key === 'Enter') {
        event.preventDefault()
        activateHighlighted()
        return
    }

    if (!totalChoices.value) {
        return
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault()
        highlightedIndex.value = highlightedIndex.value >= totalChoices.value - 1
            ? 0
            : highlightedIndex.value + 1
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()
        highlightedIndex.value = highlightedIndex.value <= 0
            ? totalChoices.value - 1
            : highlightedIndex.value - 1
    }
}

function activateHighlighted() {
    if (highlightedIndex.value < 0) {
        return
    }

    if (props.actionLabel && highlightedIndex.value === 0) {
        triggerAction()
        return
    }

    const option = filteredOptions.value[highlightedIndex.value - actionOffset.value]
    if (option) {
        selectOption(option)
    }
}

function selectOption(option) {
    const label = optionLabel(option)

    emit('update:modelValue', optionValue(option))
    emit('select', option)
    query.value = label
    lastSelectedLabel.value = label
    closeDropdown()
    nextTick(() => inputRef.value?.focus())
}

function triggerAction() {
    query.value = ''
    lastSelectedLabel.value = ''
    closeDropdown()
    emit('action')
}

async function scrollHighlightedIntoView() {
    await nextTick()

    const host = props.portal ? dropdownRef.value : rootRef.value

    if (!host || highlightedIndex.value < 0) {
        return
    }

    host
        .querySelector(`#${activeDescendant.value}`)
        ?.scrollIntoView({ block: 'nearest' })
}

function handleOutsideClick(event) {
    if (
        !rootRef.value?.contains(event.target)
        && !dropdownRef.value?.contains(event.target)
    ) {
        closeDropdown()
    }
}

function updateDropdownPosition() {
    if (!props.portal || !inputRef.value) {
        dropdownStyle.value = {}
        return
    }

    const rect = inputRef.value.getBoundingClientRect()
    const gap = 8
    const viewportPadding = 16
    const maxHeight = 288
    const spaceBelow = window.innerHeight - rect.bottom - viewportPadding - gap
    const spaceAbove = rect.top - viewportPadding - gap
    const openAbove = spaceBelow < 180 && spaceAbove > spaceBelow
    const availableHeight = Math.max(160, Math.min(maxHeight, openAbove ? spaceAbove : spaceBelow))

    dropdownStyle.value = {
        left: `${Math.max(viewportPadding, rect.left)}px`,
        minWidth: `${rect.width}px`,
        maxWidth: `calc(100vw - ${viewportPadding * 2}px)`,
        maxHeight: `${availableHeight}px`,
        ...(props.dropdownWidth === 'content' ? { width: 'max-content' } : { width: `${rect.width}px` }),
        ...(openAbove
            ? { bottom: `${Math.max(viewportPadding, window.innerHeight - rect.top + gap)}px` }
            : { top: `${rect.bottom + gap}px` }),
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleOutsideClick)
    window.addEventListener('resize', updateDropdownPosition)
    document.addEventListener('scroll', updateDropdownPosition, true)
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleOutsideClick)
    window.removeEventListener('resize', updateDropdownPosition)
    document.removeEventListener('scroll', updateDropdownPosition, true)
})
</script>

<template>
    <div ref="rootRef" class="relative w-full min-w-0">
        <div class="relative min-w-0">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
                :id="inputId"
                ref="inputRef"
                v-model="query"
                type="search"
                autocomplete="off"
                :placeholder="placeholder"
                role="combobox"
                :aria-expanded="isOpen"
                :aria-controls="listboxId"
                :aria-activedescendant="activeDescendant"
                class="block w-full min-w-0 rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-10 text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                @focus="openDropdown"
                @input="handleInput"
                @keydown="handleKeydown"
            />
            <button
                type="button"
                class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                aria-label="Toggle options"
                @click="toggleDropdown"
            >
                <ChevronDown class="h-4 w-4 transition" :class="{ 'rotate-180': isOpen }" />
            </button>
        </div>

        <Teleport to="body" :disabled="!portal">
            <div
                v-if="isOpen"
                :id="listboxId"
                ref="dropdownRef"
                class="overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                :class="[
                    portal ? 'fixed z-[1000]' : 'z-30 max-h-72',
                    !portal && (dropdownPosition === 'static' ? 'mt-2' : 'absolute left-0 mt-2'),
                    dropdownWidth === 'content' ? 'min-w-full w-max max-w-[calc(100vw-2rem)] overflow-x-auto' : 'w-full',
                ]"
                :style="portal ? dropdownStyle : undefined"
                role="listbox"
                :aria-labelledby="inputId"
            >
                <button
                    v-if="actionLabel"
                    :id="`${inputId}-action`"
                    type="button"
                    class="flex min-w-full items-center gap-3 px-3 py-2 text-left text-sm font-medium text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-gray-800"
                    :class="highlightedIndex === 0 ? 'bg-gray-100 dark:bg-gray-800' : ''"
                    role="option"
                    :aria-selected="highlightedIndex === 0"
                    @mouseenter="highlightedIndex = 0"
                    @click="triggerAction"
                >
                    <Plus class="h-4 w-4 shrink-0" />
                    <span :class="dropdownWidth === 'content' ? 'whitespace-nowrap' : 'min-w-0 truncate'">
                        {{ actionLabel }}
                    </span>
                </button>

                <button
                    v-for="(option, index) in filteredOptions"
                    :id="optionId(option)"
                    :key="optionValue(option)"
                    type="button"
                    class="flex min-w-full items-start gap-3 px-3 py-2 text-left text-sm transition hover:bg-blue-50 dark:hover:bg-gray-800"
                    :class="[
                        isSelected(option) ? 'bg-blue-50 dark:bg-blue-900/30' : '',
                        highlightedIndex === index + actionOffset ? 'bg-gray-100 dark:bg-gray-800' : '',
                    ]"
                    role="option"
                    :aria-selected="isSelected(option)"
                    @mouseenter="highlightedIndex = index + actionOffset"
                    @click="selectOption(option)"
                >
                    <Check
                        class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"
                        :class="isSelected(option) ? 'opacity-100' : 'opacity-0'"
                    />
                    <span :class="dropdownWidth === 'content' ? 'shrink-0' : 'min-w-0 flex-1'">
                        <span
                            class="block font-medium text-gray-900 dark:text-gray-100"
                            :class="dropdownWidth === 'content' ? 'whitespace-nowrap' : 'truncate'"
                        >
                            {{ optionLabel(option) }}
                        </span>
                        <span
                            v-if="optionMeta(option)"
                            class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400"
                            :class="dropdownWidth === 'content' ? 'whitespace-nowrap' : 'truncate'"
                        >
                            {{ optionMeta(option) }}
                        </span>
                    </span>
                </button>

                <div
                    v-if="filteredOptions.length === 0"
                    class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ emptyLabel }}
                </div>
            </div>
        </Teleport>
    </div>
</template>
