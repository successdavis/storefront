<script setup lang="ts">
import {ref} from "vue";
import Navigation from "@/components/Welcome/Navigation.vue";
import Footer from "@/components/Welcome/Footer.vue";
import ProductPage from "@/components/Welcome/Product/ProductPage.vue";
import ProductDetails from "@/components/Welcome/Product/ProductDetails.vue";
import TechSpecs from "@/components/Welcome/Product/TechSpecs.vue";

const navLinks =
    [
        {
            text: 'Home',
            url: '/'
        },
        {
            text: 'Services',
            url: '/services',
            children: [
                {
                    text: 'Web Development',
                    url: '/services/web'
                },
                {
                    text: 'UI Design',
                    url: '/services/ui'
                }
            ]
        },
        {
            text: 'Contact',
            url: '/contact'
        },
        {
            text: 'Home',
            url: '/'
        },
        {
            text: 'Services',
            url: '/services',
            children: [
                {
                    text: 'Web Development',
                    url: '/services/web'
                },
                {
                    text: 'UI Design',
                    url: '/services/ui'
                }
            ]
        },
        {
            text: 'Contact',
            url: '/contact'
        },
        {
            text: 'Home',
            url: '/'
        },
        {
            text: 'Services',
            url: '/services',
            children: [
                {
                    text: 'Web Development',
                    url: '/services/web'
                },
                {
                    text: 'UI Design',
                    url: '/services/ui'
                }
            ]
        },
        {
            text: 'Contact',
            url: '/contact'
        },
        {
            text: 'Home',
            url: '/'
        },
        {
            text: 'Services',
            url: '/services',
            children: [
                {
                    text: 'Web Development',
                    url: '/services/web'
                },
                {
                    text: 'UI Design',
                    url: '/services/ui'
                },
                {
                    text: 'Web Development',
                    url: '/services/web'
                },
            ]
        },
        {
            text: 'Contact',
            url: '/contact'
        }
    ]
let activeKey = ref(null)

function toggle(index) {
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
    <Navigation
        :navLinks="navLinks"
    />

    <div class="mx-auto mx-6 py-2 px-6 md:px-32 w-full ">
<div class="md:flex ">

    <ProductPage
        product-name="UnNamed product"
    />

    <ProductDetails />
</div>
    <TechSpecs />
    </div>


    <div>
        <Footer

        />

    </div>
</template>
