import { reactive, ref, computed, watch } from 'vue'

export function useVariantSelection(props, rows, emit, revokePreview, pruneSkuStatus) {
    const state = reactive({ selected: {} }) // Map typeId -> [valueId]
    const selectionDirty = ref(false)

    // selection helpers
    function toggleValue(typeId, valueId) {
        selectionDirty.value = true
        const key = String(typeId)
        const vid = String(valueId)
        const arr = (state.selected[key] || []).map(String)
        const has = arr.includes(vid)
        state.selected[key] = has ? arr.filter(v => v !== vid) : [...arr, vid]
        if (!state.selected[key].length) delete state.selected[key]
    }
    function isActive(typeId, valueId) {
        const key = String(typeId)
        const vid = String(valueId)
        return (state.selected[key] || []).map(String).includes(vid)
    }

    // resolve selected groups in UI order
    const groupsForCombos = computed(() => {
        const out = []
        for (const t of (props.variantTypes || [])) {
            const arr = (state.selected[String(t.id)] || []).map(String)
            if (arr.length) out.push(arr)
        }
        return out
    })

    // cartesian product
    function combinations(valueGroups) {
        if (!valueGroups.length) return []
        const [head, ...tail] = valueGroups
        const tailCombos = combinations(tail)
        return tailCombos.length ? head.flatMap(h => tailCombos.map(tc => [h, ...tc])) : head.map(h => [h])
    }
    const allCombos = computed(() => combinations(groupsForCombos.value))

    // seed selection from existing rows so tabs do not explode variants
    function seedSelectionFromRows() {
        const next = {}
        for (const r of rows) {
            for (const vid of (r.value_ids || [])) {
                const svid = String(vid)
                const type = (props.variantTypes || []).find(t =>
                    (t.values || []).some(v => String(v.id) === svid)
                )
                if (!type) continue
                const tid = String(type.id)
                if (!next[tid]) next[tid] = new Set()
                next[tid].add(svid)
            }
        }
        for (const [tid, set] of Object.entries(next)) {
            if (!state.selected[tid]?.length) state.selected[tid] = Array.from(set)
        }
    }
    function ensureSelectionSeeded() {
        if (Object.keys(state.selected).length === 0 && rows.length > 0 && (props.variantTypes?.length || 0) > 0) {
            seedSelectionFromRows()
        }
    }

    // generate rows from combos, preserving existing ones
    function generateRows() {
        if (!selectionDirty.value) return
        const combos = allCombos.value || []
        const keyFromRow = v => JSON.stringify((v.value_ids || []).map(id => String(id)).sort())
        const existing = new Map(rows.map(v => [keyFromRow(v), v]))
        const created = []

        for (const combo of combos) {
            const value_ids = combo.slice()
            const key = JSON.stringify(value_ids.map(id => String(id)).sort())
            if (existing.has(key)) {
                created.push(existing.get(key))
            } else {
                created.push({
                    id: null,
                    sku: '',
                    quantity: 0,
                    barcode: '',
                    last_purchase_price: null,
                    regular_price: null,
                    sale_starts_at: null,
                    sale_ends_at: null,
                    weight: null, length: null, width: null, height: null,
                    value_ids,
                    images: [],
                    _objectURL: '',
                })
            }
        }

        rows.splice(0, rows.length, ...created)
        pruneSkuStatus()
        emit('update:modelValue', created)
    }
    watch(allCombos, generateRows)

    // collapse existing rows to allowed value ids and dedupe
    function collapseRowsToAllowed(allowedValueIds) {
        const byKey = new Map()
        for (const r of rows) {
            const filtered = (r.value_ids || [])
                .map(String)
                .filter(id => allowedValueIds.has(id))
            if (filtered.length === 0) continue

            const sorted = [...filtered].sort()
            const key = JSON.stringify(sorted)

            if (!byKey.has(key)) {
                r.value_ids = sorted
                byKey.set(key, r)
            } else {
                const current = byKey.get(key)
                const keep = current?.id ? current : (r?.id ? r : current)
                byKey.set(key, keep)
            }
        }
        return Array.from(byKey.values())
    }

    // keep local rows in sync with v-model and types
    watch(
        () => props.modelValue,
        v => {
            rows.splice(0, rows.length, ...(Array.isArray(v) ? v : []))
            pruneSkuStatus()
            ensureSelectionSeeded()
        },
        { immediate: true }
    )

    watch(
        () => props.variantTypes,
        (newTypes) => {
            ensureSelectionSeeded()

            const types = Array.isArray(newTypes) ? newTypes : []
            const allowedTypeIds = types.map(t => String(t.id))

            // prune selection
            for (const key of Object.keys(state.selected)) {
                if (!allowedTypeIds.includes(key)) {
                    delete state.selected[key]
                    continue
                }
                const type = types.find(t => String(t.id) === key)
                const allowedVals = new Set((type?.values || []).map(v => String(v.id)))
                state.selected[key] = (state.selected[key] || []).map(String).filter(v => allowedVals.has(v))
                if (!state.selected[key]?.length) delete state.selected[key]
            }

            // if no values remain at all, clear rows
            const allowedValueIds = new Set(types.flatMap(t => (t.values || []).map(v => String(v.id))))
            if (allowedValueIds.size === 0) {
                rows.forEach(r => revokePreview(r))
                rows.splice(0, rows.length)
                pruneSkuStatus()
                emit('update:modelValue', [])
                return
            }

            // collapse instead of dropping all
            const next = collapseRowsToAllowed(allowedValueIds)

            // revoke previews for rows that will disappear
            rows.filter(r => !next.includes(r)).forEach(r => revokePreview(r))

            rows.splice(0, rows.length, ...next)
            pruneSkuStatus()
            emit('update:modelValue', [...rows])
        },
        { deep: true, immediate: true }
    )

    function resolveValueNames(valueIds) {
        const map = new Map()
        for (const type of props.variantTypes || []) {
            for (const val of (type?.values || [])) {
                map.set(String(val.id), { type: type.name, value: val.value })
            }
        }
        return (valueIds || []).map(id => map.get(String(id))?.value ?? String(id))
    }

    return {
        state, selectionDirty, toggleValue, isActive,
        resolveValueNames
    }
}
