<script setup lang="ts">
import {ref} from "vue";
import Navigation from "@/components/Welcome/Navigation.vue";
import NavigationLink from "@/components/Welcome/NavigationLink.vue";
import Headings from "@/components/Welcome/Headings.vue";
import FilterBar from "@/components/Welcome/FilterBar.vue";
import Sliders from "@/components/Welcome/Sliders.vue";
import Card from "@/components/Welcome/ProductCards/Card.vue"
import Footer from "@/components/Welcome/Footer.vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    homeLinks: {
        type: Array,
        default: () => [
            { text: 'Home', url: '/' },
            { text: 'Collection', url: '/collection' },
            { text: 'Phones & Tablets', url: '/tablets' }
        ]
    },

    productSections: {
        type: Array,
        default: () => [
            {
                sectionTitle: 'Phones & Tablets',
                products: [
                    {
                        id: 1,
                        title: 'Samsung Galaxy Tab 4',
                        price: '₦120,000',
                        isSale: true,
                        images: [
                            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
                            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
                            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200'
                        ],
                        specifications: [
                            { icon: 'cpu', label: 'Processor', value: 'Intel i7' },
                            { icon: 'ram', label: 'RAM', value: '16GB' },
                            { icon: 'storage', label: 'Storage', value: '512GB SSD' },
                            { icon: 'display', label: 'Display', value: '15.6 inch' }
                        ],
                        rating: 4.5,
                        stock: 12
                    },
                    {
                        id: 2,
                        title: 'HP EliteBook',
                        price: '₦350,000',
                        isSale: true,
                        images: [
                            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
                            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
                            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
                        ],
                        specifications: [
                            { icon: 'cpu', label: 'Processor', value: 'Intel i5' },
                            { icon: 'ram', label: 'RAM', value: '8GB' },
                            { icon: 'storage', label: 'Storage', value: '256GB SSD' }
                        ],
                        rating: 4.2,
                        stock: 5
                    }
                ]
            },
            {
                sectionTitle: 'Laptops',
                products: [
                    // other laptop products here
                ]
            }
        ]
    }
})

let activeKey = ref(null)
const toggle = index => {
    activeKey.value = activeKey.value === index ? null : index
}
</script>

<script lang="ts">
// 👇 tell Inertia to use AuthBase instead of the global layout
import GuestLayout from "@/layouts/GuestLayout.vue";

export default {
    layout: GuestLayout
}
</script>

<template>
    <Navigation />
    <div class="mx-auto mx-6 py-2 px-6 md:px-32 w-full ">
        <div class="md:flex md:items-start md:justify-between gap-12 py-16">

            <div class="space-y-8 md:max-w-xl">
                <Headings
                    title="StechMax Store"
                    class="text-3xl md:text-6xl font-black tracking-tight"
                />

                <button
                    class="px-6 py-2 rounded-md border-2 border-primary text-primary transition-colors duration-200 hover:bg-primary hover:text-secondary"
                >
                    Our story
                </button>
            </div>

            <div class="space-y-6 md:max-w-lg mt-10 md:mt-0">
                <p class="text-sm leading-relaxed">
                    <strong class="font-medium text-lg">StechMax Store</strong> is the smarter choice for tech shopping.
                    We design your experience to feel calm, clear, and convenient.
                </p>

                <p class="text-sm leading-relaxed">
                    You get simple navigation, tailored recommendations, and a transparent process that keeps you in
                    control. You avoid confusion and find what you need without stress.
                </p>

                <p class="text-sm leading-relaxed">
                    Choose Stechmax Store. Innovation meets intuitive shopping.
                </p>
            </div>

        </div>
    </div>

    <!--    Product display section-->
    <div
        v-for="section in productSections"
        :key="section.sectionTitle"
    >
        <!-- Section Heading -->
        <Headings
            v-if=" section?.products?.length > 0"
            :title="section?.sectionTitle"
            class="text-3xl my-2 md:text-6xl font-black tracking-tight rounded-md px-6 md:px-32 w-full"
        />

        <!-- Product Grid -->
        <div
            class="rounded-md px-6 md:px-32 w-full grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"
        >
            <Card
                v-for="product in section.products"
                :key="product.id"
                :images="product.images"
                :title="product.title"
                :price="product.price"
                :onSale="product.isSale"
                :specifications="product.specifications"
            />
        </div>

        <!-- View All Button -->
        <div
            v-if=" section?.products?.length > 0"
            class="w-full flex items-center justify-center my-2 rounded-md">
            <Link
                :href="`/category_products/${encodeURIComponent(section.sectionTitle)}`"
                class="px-6 py-2 border w-fit h-fit border-primary text-primary transition-colors duration-200 hover:bg-primary hover:text-secondary"
            >
                View all
            </Link>
        </div>
    </div>



    <div>
        <Footer

        />

    </div>
</template>
