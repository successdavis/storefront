<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import AppLogo from "@/components/AppLogo.vue"
import NavigationLink from "@/components/Welcome/NavigationLink.vue"
import NavActions from "@/components/Welcome/NavActions.vue"
import MobileNavigation from "@/components/Welcome/MobileNavigation.vue";

const  navLinks =
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

const activeKey = ref(null)

function toggle(index) {
    activeKey.value = activeKey.value === index ? null : index
}


function closeAll() {
    activeKey.value = null
}

function handleOutsideClick(e) {
    if (!e.target.closest('[data-navigation]')) {
        closeAll()
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick)
})
</script>

<template>
    <header class="px-6 md:px-32 ">
        <nav class="border-b" data-navigation>
            <div class="flex items-center gap-6 py-4 justify-between md:justify-center">
                <MobileNavigation :navLinks="navLinks" />

                <AppLogo class="shrink-0" />
                <div class="hidden flex-1 md:flex justify-center overflow-">
                    <ul
                        v-if="navLinks && navLinks.length > 0"
                        class="flex flex-wrap justify-center gap-2 max-w-full"
                    >
                        <NavigationLink
                            v-for="(link, index) in navLinks"
                            :key="index"
                            :link="link"
                            :open="activeKey === index"
                            @toggle="toggle(index)"
                        />

                    </ul>
                </div>

                <NavActions class="shrink-0" />

            </div>
        </nav>
    </header>
</template>
