import { reactive, computed } from 'vue'

export function useValidation(rows) {
    const errors = reactive({ table: {}, modal: {} })

    function setErr(scope, idx, field, msg) {
        if (!errors[scope][idx]) errors[scope][idx] = {}
        if (msg) errors[scope][idx][field] = msg
        else if (errors[scope][idx]) delete errors[scope][idx][field]
        if (errors[scope][idx] && Object.keys(errors[scope][idx]).length === 0) delete errors[scope][idx]
    }

    function validateNonNegNumber(n, allowNull = false) {
        if (n === null || n === '' || typeof n === 'undefined') return allowNull ? '' : 'This field is required'
        if (Number.isNaN(Number(n))) return 'Enter a number'
        if (Number(n) < 0) return 'Must be zero or greater'
        return ''
    }

    function validateTableRow(idx) {
        const r = rows[idx]; if (!r) return
        const qErr = validateNonNegNumber(r.quantity, false) || (!Number.isInteger(Number(r.quantity)) ? 'Must be an integer' : '')
        setErr('table', idx, 'quantity', qErr)
        setErr('table', idx, 'regular_price', validateNonNegNumber(r.regular_price, false))
        if (r.barcode) {
            const ok = /^[A-Za-z0-9\-_.]+$/.test(String(r.barcode))
            setErr('table', idx, 'barcode', ok ? '' : 'Only letters, digits, dash, underscore, dot')
        } else {
            setErr('table', idx, 'barcode', '')
        }
    }

    const tableErrorCount = computed(() =>
        Object.values(errors.table).reduce((sum, m) => sum + Object.keys(m).length, 0)
    )

    return { errors, setErr, validateNonNegNumber, validateTableRow, tableErrorCount }
}
