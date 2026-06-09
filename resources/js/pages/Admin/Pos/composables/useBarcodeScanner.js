import { computed, onBeforeUnmount, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { useCart } from './useCart'

const MIN_SCAN_LENGTH = 6
const MAX_SCAN_DURATION_MS = 900
const MAX_KEY_GAP_MS = 80

export function useBarcodeScanner() {
    const page = usePage()
    const { addToCart } = useCart()
    const variants = computed(() => page.props.variants?.data ?? [])

    let buffer = ''
    let firstKeyAt = 0
    let lastKeyAt = 0
    let editableSnapshot = null

    const normalize = (value) => String(value ?? '').trim().toLowerCase()
    const productsApiUrl = computed(() => page.props.pos_routes?.products_api ?? '/admin/pos/products')

    const isEditableElement = (target) => {
        if (typeof Element === 'undefined' || !(target instanceof Element)) return false

        return Boolean(target.closest('input, textarea, [contenteditable="true"]'))
    }

    const getInputSelection = (element) => {
        try {
            return {
                selectionStart: element.selectionStart,
                selectionEnd: element.selectionEnd,
            }
        } catch {
            return {
                selectionStart: null,
                selectionEnd: null,
            }
        }
    }

    const captureEditableSnapshot = (target) => {
        if (typeof Element === 'undefined' || !(target instanceof Element)) return null

        const element = target.closest('input, textarea, [contenteditable="true"]')
        if (!element) return null

        if ('value' in element) {
            const selection = getInputSelection(element)

            return {
                element,
                value: element.value,
                selectionStart: selection.selectionStart,
                selectionEnd: selection.selectionEnd,
            }
        }

        return {
            element,
            textContent: element.textContent,
        }
    }

    const restoreEditableSnapshot = (snapshot) => {
        if (!snapshot?.element) return

        if ('value' in snapshot.element) {
            snapshot.element.value = snapshot.value

            if (
                typeof snapshot.element.setSelectionRange === 'function'
                && snapshot.selectionStart !== null
                && snapshot.selectionEnd !== null
            ) {
                snapshot.element.setSelectionRange(snapshot.selectionStart, snapshot.selectionEnd)
            }

            snapshot.element.dispatchEvent(new Event('input', { bubbles: true }))
            return
        }

        snapshot.element.textContent = snapshot.textContent
        snapshot.element.dispatchEvent(new Event('input', { bubbles: true }))
    }

    const resetBuffer = () => {
        buffer = ''
        firstKeyAt = 0
        lastKeyAt = 0
        editableSnapshot = null
    }

    const isScannerInput = (now) => {
        return buffer.length >= MIN_SCAN_LENGTH && firstKeyAt > 0 && now - firstKeyAt <= MAX_SCAN_DURATION_MS
    }

    const matchesScannedCode = (variant, scannedCode) => {
        const code = normalize(scannedCode)

        return normalize(variant?.barcode) === code || normalize(variant?.sku) === code
    }

    const findLoadedVariant = (scannedCode) => {
        return variants.value.find((variant) => matchesScannedCode(variant, scannedCode)) ?? null
    }

    const fetchVariantByBarcode = async (scannedCode) => {
        const response = await axios.get(productsApiUrl.value, {
            params: { barcode: scannedCode },
        })

        const products = response.data?.data ?? []

        return products.find((variant) => matchesScannedCode(variant, scannedCode)) ?? null
    }

    const handleScan = async (scannedCode) => {
        const code = String(scannedCode ?? '').trim()
        if (!code) return

        const loadedVariant = findLoadedVariant(code)
        if (loadedVariant) {
            addToCart(loadedVariant)
            return
        }

        try {
            const fetchedVariant = await fetchVariantByBarcode(code)
            if (fetchedVariant) {
                addToCart(fetchedVariant)
            } else {
                console.warn(`No POS product variant found for barcode: ${code}`)
            }
        } catch (error) {
            console.error('POS barcode lookup failed', error)
        }
    }

    const completeScan = (event, now) => {
        if (!isScannerInput(now)) {
            resetBuffer()
            return
        }

        const scannedCode = buffer
        const snapshot = editableSnapshot
        event.preventDefault()
        event.stopPropagation()
        resetBuffer()
        restoreEditableSnapshot(snapshot)
        void handleScan(scannedCode)
    }

    const handleKeydown = (event) => {
        if (event.ctrlKey || event.metaKey || event.altKey) return

        const now = Date.now()
        const key = event.key

        if (key === 'Enter' || key === 'NumpadEnter' || key === 'Tab') {
            completeScan(event, now)
            return
        }

        if (key.length !== 1) return

        if (!firstKeyAt || now - lastKeyAt > MAX_KEY_GAP_MS) {
            resetBuffer()
            firstKeyAt = now
            editableSnapshot = isEditableElement(event.target)
                ? captureEditableSnapshot(event.target)
                : null
        }

        buffer += key
        lastKeyAt = now

        if (isScannerInput(now)) {
            event.preventDefault()
            event.stopPropagation()
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown, true)
    })

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleKeydown, true)
    })

    return {
        handleScan,
    }
}
