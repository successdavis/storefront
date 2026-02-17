<template>
    <div class="product-details mb-6 overflow-y-auto max-h-[600px] scrollbar-thin scrollbar-thumb-gray-300 pr-2">
        <!-- Product Header -->
        <ProductHeader
            :product="product"
            :stock-status="stockStatus"
        />

        <!-- Upgrade Options -->
        <UpgradeOptions
            v-model:storage="selectedStorage"
            v-model:ram="selectedRAM"
            :storage-options="storageOptions"
            :ram-options="ramOptions"
        />

        <!-- Accessories Section -->
        <AccessoriesSection
            v-model:selected-accessories="selectedAccessories"
            :accessories="availableAccessories"
        />

        <!-- Quantity Selector -->
        <QuantitySelector
            v-model="quantity"
        />

        <!-- Price Summary -->
        <PriceSummary
            :base-price="product.price"
            :quantity="quantity"
            :storage-upgrade-price="storageUpgradePrice"
            :ram-upgrade-price="ramUpgradePrice"
            :accessories-price="accessoriesTotalPrice"
            :currency="product.currency"
        />

        <!-- Action Buttons -->
        <ActionButtons
            :total-price="totalPrice"
            :currency="product.currency"
            @add-to-cart="handleAddToCart"
            @add-to-wishlist="handleAddToWishlist"
            @buy-now="handleBuyNow"
        />

        <!-- Pickup Information -->
        <PickupInfo :shipping-info="shippingInfo"/>

        <!-- Share Buttons -->
        <ShareSection />

        <!-- Shipping Info -->
        <ShippingInfo
            :shipping-info="shippingInfo"
            :warranty="warranty"
        />

        <!-- Help Button -->
<!--        <HelpButton/>-->
    </div>
</template>

<script setup>
import {ref, computed} from 'vue'
import ProductHeader from './ProductDetailsComps/ProductHeader.vue'
import UpgradeOptions from './ProductDetailsComps/UpgradeOptions.vue'
import AccessoriesSection from './ProductDetailsComps/AccessoriesSection.vue'
import QuantitySelector from './ProductDetailsComps/QuantitySelector.vue'
import PriceSummary from './ProductDetailsComps/PriceSummary.vue'
import ActionButtons from './ProductDetailsComps/ActionButtons.vue'
import PickupInfo from './ProductDetailsComps/PickupInfo.vue'
import ShareSection from './ProductDetailsComps/ShareSection.vue'
import ShippingInfo from './ProductDetailsComps/ShippingInfo.vue'
import HelpButton from './ProductDetailsComps/HelpButton.vue'

const props = defineProps({
    product: {
        type: Object,
        required: true,
        default: () => ({name: 'Elite book pro', price: 398500, currency: '₦'})
    },
    stockStatus: {
        type: Object,
        required: true,
        default: () => ({available: true, message: 'Low stock'})
    },
    shippingInfo: {
        type: Object,
        required: true,
        default: () => ({
            shipping: 'Complimentary shipping & returns',
            inStock: true,
            shipsWithin: '1-2 business days',
            pickup: {available: false, location: 'Abuja, FCT'}
        })
    },
    warranty: {
        type: String,
        required: true,
        default: '30 days Warranty'
    }
})

// State
const quantity = ref(1)
const selectedStorage = ref('')
const selectedRAM = ref('')
const selectedAccessories = ref([])

// Options
const storageOptions = ref([
    {value: '', label: 'Select storage options', price: 0},
    {value: '256gb', label: '256GB SSD', price: 0},
    {value: '512gb', label: '512GB SSD', price: 50000},
    {value: '1tb', label: '1TB SSD', price: 100000},
])

const ramOptions = ref([
    {value: '', label: 'Select RAM options', price: 0},
    {value: '16gb', label: '16GB RAM', price: 0},
    {value: '32gb', label: '32GB RAM', price: 40000},
])

const availableAccessories = ref([
    {id: 'mouse', name: 'Wireless Mouse', price: 15000, image: '/images/mouse.jpg'},
    {id: 'keyboard', name: 'Mechanical Keyboard', price: 45000, image: '/images/keyboard.jpg'},
    {id: 'charger', name: 'Extra Charger', price: 25000, image: '/images/charger.jpg'},
    {id: 'bag', name: 'Laptop Bag', price: 18000, image: '/images/bag.jpg'},
    {id: 'usb-hub', name: 'USB-C Hub', price: 22000, image: '/images/usb-hub.jpg'},
])

// Computed prices
const storageUpgradePrice = computed(() => {
    const option = storageOptions.value.find(opt => opt.value === selectedStorage.value)
    return option ? option.price : 0
})

const ramUpgradePrice = computed(() => {
    const option = ramOptions.value.find(opt => opt.value === selectedRAM.value)
    return option ? option.price : 0
})

const accessoriesTotalPrice = computed(() => {
    return selectedAccessories.value.reduce((total, accessoryId) => {
        const accessory = availableAccessories.value.find(acc => acc.id === accessoryId)
        return total + (accessory ? accessory.price : 0)
    }, 0)
})

const totalPrice = computed(() => {
    const basePrice = props.product.price
    const upgradesPrice = storageUpgradePrice.value + ramUpgradePrice.value
    const accessoriesPrice = accessoriesTotalPrice.value
    return (basePrice + upgradesPrice + accessoriesPrice) * quantity.value
})

// Handlers
const handleAddToCart = () => {
    const cartItem = {
        product: props.product,
        quantity: quantity.value,
        storage: selectedStorage.value,
        ram: selectedRAM.value,
        accessories: selectedAccessories.value.map(id =>
            availableAccessories.value.find(acc => acc.id === id)
        ),
        totalPrice: totalPrice.value
    }
    console.log('Adding to cart:', cartItem)
    // Emit event or call your cart store/API
}

const handleAddToWishlist = () => {
    console.log('Adding to wishlist')
    // Emit event or call your wishlist store/API
}

const handleBuyNow = () => {
    console.log('Buy now')
    // Emit event or navigate to checkout
}
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>
