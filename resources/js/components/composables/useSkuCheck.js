import { reactive } from 'vue'

export function useSkuCheck(props, rows, setErr, validateTableRow) {
    const skuStatus = reactive({})
    const skuTimers = new Map()
    const skuControllers = new Map()

    function setSkuState(i, patch) {
        if (!skuStatus[i]) skuStatus[i] = { loading: false, available: null, suggestion: null, message: null }
        Object.assign(skuStatus[i], patch)
    }
    function clearSkuState(i) {
        if (skuTimers.has(i)) { clearTimeout(skuTimers.get(i)); skuTimers.delete(i) }
        const ctl = skuControllers.get(i)
        if (ctl) { try { ctl.abort() } catch {} skuControllers.delete(i) }
        delete skuStatus[i]
    }
    function pruneSkuStatus() {
        const valid = new Set(rows.map((_, idx) => String(idx)))
        Object.keys(skuStatus).forEach(k => { if (!valid.has(String(k))) delete skuStatus[k] })
    }
    function suggestNextSku(raw) {
        const s = String(raw || '').trim()
        if (!s) return ''
        const m = s.match(/^(.*?)-(\d{3,})$/)
        if (!m) return `${s}-001`
        const base = m[1]
        const n = String(parseInt(m[2], 10) + 1)
        return `${base}-${n.padStart(m[2].length, '0')}`
    }
    function onSkuInput(i) {
        if (skuTimers.has(i)) { clearTimeout(skuTimers.get(i)); skuTimers.delete(i) }
        setSkuState(i, { loading: true, message: null })
        skuTimers.set(i, setTimeout(() => doSkuCheck(i), 350))
    }
    async function doSkuCheck(i) {
        const prev = skuControllers.get(i)
        if (prev) { try { prev.abort() } catch {} skuControllers.delete(i) }
        const row = rows[i]; if (!row) return
        const sku = String(row.sku || '').trim()
        if (!sku) { setSkuState(i, { loading: false, available: null, suggestion: null, message: null }); setErr('table', i, 'sku', 'SKU is required'); return }
        const ctl = new AbortController()
        skuControllers.set(i, ctl)
        const qs = new URLSearchParams()
        qs.set('sku', sku)
        if (row.id) qs.set('ignore_id', row.id)
        try {
            const res = await fetch(`${props.skuCheckUrl}?${qs.toString()}`, { headers: { 'Accept': 'application/json' }, signal: ctl.signal })
            if (!res.ok) throw new Error(`HTTP ${res.status}`)
            const data = await res.json()
            const available = !!data.available
            const serverSuggestion = data.suggestion || data.sku || null
            if (available) { setSkuState(i, { loading: false, available: true, suggestion: null, message: 'Available' }); setErr('table', i, 'sku', '') }
            else { const suggestion = serverSuggestion || suggestNextSku(sku); setSkuState(i, { loading: false, available: false, suggestion, message: 'SKU already in use' }); setErr('table', i, 'sku', 'SKU already in use') }
        } catch (e) {
            if (e?.name !== 'AbortError') setSkuState(i, { loading: false, available: null, suggestion: null, message: 'Could not verify SKU now' })
        } finally { skuControllers.delete(i) }
    }
    function applySuggestedSku(i) {
        const st = skuStatus[i]; if (!st?.suggestion) return
        rows[i].sku = st.suggestion
        validateTableRow(i)
        onSkuInput(i)
    }

    return { skuStatus, onSkuInput, applySuggestedSku, clearSkuState, pruneSkuStatus }
}
