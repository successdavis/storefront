<script setup lang="ts">
import {ref} from "vue";
import Navigation from "@/components/Welcome/Navigation.vue";
import NavigationLink from "@/components/Welcome/NavigationLink.vue";
import Headings from "@/components/Welcome/Headings.vue";
import FilterBar from "@/components/Welcome/FilterBar.vue";
import Sliders from "@/components/Welcome/Sliders.vue";
import Card from "@/components/Welcome/ProductCards/Card.vue"
import Footer from "@/components/Welcome/Footer.vue";

let activeKey = ref(null)
const  homeLinks =
    [
        {
            text: 'Home',
            url: '/'
        },
        {
            text: 'Collection',
            url: '/collection'
        },
        {
            text: 'Phones & Tablets',
            url: '/tablets'
        }
    ]
function toggle(index) {
    activeKey.value = activeKey.value === index ? null : index
}
const products = [
    {
        id: 1,
        title: 'Samsung Galaxy Tab 4',
        price: '₦120,000',
        isSale: true,
        images: [
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-1651956.jpg?v=1769613563&width=1200',
        ],
        specifications: [
            {
                icon: 'cpu',
                label: 'Processor',
                value: 'Intel i7',
            },
            {
                icon: 'ram',
                label: 'RAM',
                value: '16GB',
            },
            {
                icon: 'storage',
                label: 'Storage',
                value: '512GB SSD',
            },
            {
                icon: 'display',
                label: 'Display',
                value: '15.6 inch',
            },
        ],
        rating: 4.5,
        stock: 12,
    },
    {
        id: 2,
        title: 'HP EliteBook',
        price: '₦350,000',
        isSale: true,
        images: [
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
        ],
        specifications: [
            {
                icon: 'cpu',
                label: 'Processor',
                value: 'Intel i5',
            },
            {
                icon: 'ram',
                label: 'RAM',
                value: '8GB',
            },
            {
                icon: 'storage',
                label: 'Storage',
                value: '256GB SSD',
            },
        ],
        rating: 4.2,
        stock: 5,
    },
    {
        id: 1,
        title: 'Samsung Galaxy Tab 4 h-full mx-auto rounded-md bg-white overflow-hidden shadow-md hover:shadow-2xl',
        price: '₦120,000',
        isSale: true,
        images: [
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-1651956.jpg?v=1769613563&width=1200',
        ],
        specifications: [
            {
                icon: 'cpu',
                label: 'Processor',
                value: 'Intel i7',
            },
            {
                icon: 'ram',
                label: 'RAM',
                value: '16GB',
            },
            {
                icon: 'storage',
                label: 'Storage',
                value: '512GB SSD',
            },
            {
                icon: 'display',
                label: 'Display',
                value: '15.6 inch',
            },
        ],
        rating: 4.5,
        stock: 12,
    },
    {
        id: 2,
        title: 'HP EliteBook',
        price: '₦350,000',
        isSale: true,
        images: [
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
        ],
        specifications: [
            {
                icon: 'cpu',
                label: 'Processor',
                value: 'Intel i5',
            },
            {
                icon: 'ram',
                label: 'RAM',
                value: '8GB',
            },
            {
                icon: 'storage',
                label: 'Storage',
                value: '256GB SSD',
            },
        ],
        rating: 4.2,
        stock: 5,
    },
    {
        id: 1,
        title: 'Samsung Galaxy Tab 4',
        price: '₦120,000',
        isSale: true,
        images: [
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-1651956.jpg?v=1769613563&width=1200',
        ],
        specifications: [
            {
                icon: 'cpu',
                label: 'Processor',
                value: 'Intel i7',
            },
            {
                icon: 'ram',
                label: 'RAM',
                value: '16GB',
            },
            {
                icon: 'storage',
                label: 'Storage',
                value: '512GB SSD',
            },
            {
                icon: 'display',
                label: 'Display',
                value: '15.6 inch',
            },
        ],
        rating: 4.5,
        stock: 12,
    },
    {
        id: 2,
        title: 'HP EliteBook',
        price: '₦350,000',
        isSale: true,
        images: [
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
        ],
        specifications: [
            {
                icon: 'cpu',
                label: 'Processor',
                value: 'Intel i5',
            },
            {
                icon: 'ram',
                label: 'RAM',
                value: '8GB',
            },
            {
                icon: 'storage',
                label: 'Storage',
                value: '256GB SSD',
            },
        ],
        rating: 4.2,
        stock: 5,
    },
]
let card_title = 'Phones and tablets'
</script>

<script lang="ts">
// 👇 tell Inertia to use AuthBase instead of the global layout
import GuestLayout from "@/layouts/GuestLayout.vue";
export default {
    layout: GuestLayout
}
</script>

<template>
    <Navigation
        :navLinks="navLinks"
    />
    <div class="mx-auto mx-6 py-2 px-6 md:px-32 w-full ">
        <div class="flex-1 flex justify-start">
            <ul
                v-if="homeLinks && homeLinks.length > 0"
                class="flex flex-wrap justify-start md:justify-center gap-2 mt-32 max-w-full"
            >
                <NavigationLink
                    v-for="(link, index) in homeLinks"
                    :key="index"
                    :link="link"
                    :open="activeKey === index"
                    @toggle="toggle(index)"
                />
            </ul>
        </div>
        <Headings
            title="Phones & tablets"
            class=" text-6xl md:text-8xl font-black tracking-tight"
        />

        <FilterBar />

        <!--            <Sliders />-->


    </div>
    <div class="rounded-md px-6 md:px-32 w-full min-h-screen grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <Card
            v-for="product in products"
            :images="product?.images"
            :title="product?.title"
            :price="product?.price"
            :onSale="product?.isSale"
            :specifications="product?.specifications"
        />


    </div>
    <div>
        <Footer

        />

    </div>
</template>
