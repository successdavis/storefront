import { reactive, ref } from 'vue'

export function useVariantDetailsModal(
    rows, emit, errors, setErr, validateNonNegNumber, revokePreview, normalizeToUrl
) {
    const showModal = ref(false)
    const editingIndex = ref(null)
    const draft = reactive({
        sale_price: null,
        sale_starts_at: '',
        sale_ends_at: '',
        weight: null,
        length: null,
        width: null,
        height: null,
        images: [],
        _objectURL: '',
    })

    function openDetails(row, index) {
        editingIndex.value = index
        draft.sale_price = row.sale_price ?? null
        draft.sale_starts_at = row.sale_starts_at ?? ''
        draft.sale_ends_at = row.sale_ends_at ?? ''
        draft.weight = row.weight ?? null
        draft.length = row.length ?? null
        draft.width = row.width ?? null
        draft.height = row.height ?? null
        draft.images = Array.isArray(row.images) ? [...row.images] : []
        draft._objectURL = ''
        const first = draft.images[0]
        const URLApi = globalThis?.URL || globalThis?.webkitURL
        if (first instanceof File && URLApi?.createObjectURL) {
            draft._objectURL = URLApi.createObjectURL(first)
        }
        delete errors.modal[index]
        showModal.value = true
    }

    function closeDetails() {
        if (draft._objectURL) {
            const URLApi = globalThis?.URL || globalThis?.webkitURL
            try { URLApi?.revokeObjectURL?.(draft._objectURL) } catch {}
        }
        draft._objectURL = ''
        showModal.value = false
        editingIndex.value = null
    }

    function validateModalDraft(idx) {
        setErr('modal', idx, 'sale_price', draft.sale_price === null || draft.sale_price === '' ? '' : validateNonNegNumber(draft.sale_price, false))
        setErr('modal', idx, 'sale_starts_at', '')
        setErr('modal', idx, 'sale_ends_at', '')
        if (draft.sale_starts_at && draft.sale_ends_at) {
            const s = new Date(draft.sale_starts_at)
            const e = new Date(draft.sale_ends_at)
            if (isFinite(s) && isFinite(e) && e < s) {
                setErr('modal', idx, 'sale_ends_at', 'End must be after start')
            }
        }
        setErr('modal', idx, 'weight', draft.weight === null || draft.weight === '' ? '' : validateNonNegNumber(draft.weight, false))
        setErr('modal', idx, 'length', draft.length === null || draft.length === '' ? '' : validateNonNegNumber(draft.length, false))
        setErr('modal', idx, 'width',  draft.width  === null || draft.width  === '' ? '' : validateNonNegNumber(draft.width,  false))
        setErr('modal', idx, 'height', draft.height === null || draft.height === '' ? '' : validateNonNegNumber(draft.height, false))
    }

    function applyDetails() {
        if (editingIndex.value == null) return
        const idx = editingIndex.value
        validateModalDraft(idx)
        if (errors.modal[idx] && Object.keys(errors.modal[idx]).length) return

        const target = rows[idx]; if (!target) return
        target.sale_price = (draft.sale_price === '' ? null : draft.sale_price)
        target.sale_starts_at = draft.sale_starts_at || null
        target.sale_ends_at = draft.sale_ends_at || null
        target.weight = draft.weight ?? null
        target.length = draft.length ?? null
        target.width = draft.width ?? null
        target.height = draft.height ?? null
        target.images = Array.isArray(draft.images) ? [...draft.images] : []
        revokePreview(target)

        emit('update:modelValue', rows)
        closeDetails()
    }

    function onModalFileChange(e) {
        const list = Array.from(e?.target?.files || [])
        draft.images = list
        if (draft._objectURL) {
            const URLApi = globalThis?.URL || globalThis?.webkitURL
            try { URLApi?.revokeObjectURL?.(draft._objectURL) } catch {}
        }
        draft._objectURL = ''
        const first = list[0]
        const URLApi = globalThis?.URL || globalThis?.webkitURL
        if (first instanceof File && URLApi?.createObjectURL) {
            draft._objectURL = URLApi.createObjectURL(first)
        }
    }

    function previewSrcModal() {
        if (draft._objectURL) return draft._objectURL
        const first = draft.images?.[0]
        if (!first) return ''
        if (typeof File !== 'undefined' && first instanceof File) return draft._objectURL || ''
        if (typeof first === 'string') return normalizeToUrl(first)
        if (first && typeof first === 'object') {
            if ('url' in first && first.url) return normalizeToUrl(first.url)
            if ('path' in first && first.path) return normalizeToUrl(first.path)
        }
        return ''
    }

    return {
        showModal, editingIndex, draft,
        openDetails, closeDetails, validateModalDraft,
        applyDetails, onModalFileChange, previewSrcModal
    }
}
