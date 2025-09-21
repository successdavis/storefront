import { } from 'vue'

export function useImages(props) {
    function normalizeToUrl(p) {
        if (!p) return ''
        if (/^(https?:)?\/\//.test(p) || p.startsWith('data:') || p.startsWith('blob:')) return p
        const base = String(props.storageBase || '').replace(/\/+$/, '')
        const rel  = String(p).replace(/^\/+/, '')
        return `${base}/${rel}`
    }

    function previewSrc(row) {
        if (row?._objectURL) return row._objectURL
        const first = Array.isArray(row?.images) ? row.images[0] : null
        if (!first) return ''
        if (typeof File !== 'undefined' && first instanceof File) return row._objectURL || ''
        if (typeof first === 'string') return normalizeToUrl(first)
        if (first && typeof first === 'object') {
            if ('url' in first && first.url) return normalizeToUrl(first.url)
            if ('path' in first && first.path) return normalizeToUrl(first.path)
        }
        return ''
    }

    function revokePreview(row) {
        const URLApi = globalThis?.URL || globalThis?.webkitURL
        if (row?._objectURL && URLApi?.revokeObjectURL) { try { URLApi.revokeObjectURL(row._objectURL) } catch {} }
        if (row) row._objectURL = ''
    }

    function onFileChange(row, e) {
        const list = Array.from(e?.target?.files || [])
        row.images = list
        revokePreview(row)
        const first = list[0]
        const URLApi = globalThis?.URL || globalThis?.webkitURL
        row._objectURL = first instanceof File && URLApi?.createObjectURL ? URLApi.createObjectURL(first) : ''
    }

    return { normalizeToUrl, previewSrc, revokePreview, onFileChange }
}
