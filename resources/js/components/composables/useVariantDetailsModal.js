import { reactive, ref } from 'vue'

export function useVariantDetailsModal(
    rows, emit, errors, setErr, validateNonNegNumber, revokePreview, normalizeToUrl
) {
    const showModal = ref(false)
    const editingIndex = ref(null)
    const draft = reactive({
        weight: null,
        length: null,
        width: null,
        height: null,
        images: [],
        _objectURL: '',
    })

    function openDetails(row, index) {
        editingIndex.value = index
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
