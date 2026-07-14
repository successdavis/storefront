<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItemType } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Globe, ReceiptText, ShoppingCart } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const capabilities = computed<Record<string, boolean>>(
    () => (page.props.auth as any)?.capabilities ?? (page.props.auth as any)?.user?.capabilities ?? {},
);

const workspacePrefix = computed<string | null>(() => {
    if (capabilities.value.can_access_admin) {
        return '/admin';
    }

    if (capabilities.value.can_access_sales) {
        return '/sales';
    }

    return null;
});

const showPos = computed(() => workspacePrefix.value !== null && capabilities.value.can_use_pos !== false);

const showOrders = computed(() => {
    if (workspacePrefix.value === '/admin') {
        return capabilities.value.can_manage_orders !== false;
    }

    if (workspacePrefix.value === '/sales') {
        return capabilities.value.can_view_sales_orders !== false;
    }

    return false;
});

const quickLinks = computed(() =>
    [
        {
            title: 'POS',
            href: `${workspacePrefix.value}/pos`,
            icon: ShoppingCart,
            show: showPos.value,
            external: false,
        },
        {
            title: 'View website',
            href: '/',
            icon: Globe,
            show: true,
            external: true,
        },
        {
            title: 'Orders',
            href: `${workspacePrefix.value}/orders`,
            icon: ReceiptText,
            show: showOrders.value,
            external: false,
        },
    ].filter((link) => link.show),
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <div class="ml-6 flex items-center gap-3">
                <TooltipProvider :delay-duration="0">
                    <Tooltip v-for="link in quickLinks" :key="link.title">
                        <TooltipTrigger>
                            <Button variant="ghost" size="icon" as-child class="group h-9 w-9 cursor-pointer">
                                <a v-if="link.external" :href="link.href" target="_blank" rel="noopener">
                                    <component :is="link.icon" class="size-5 opacity-80 group-hover:opacity-100" />
                                    <span class="sr-only">{{ link.title }}</span>
                                </a>
                                <Link v-else :href="link.href">
                                    <component :is="link.icon" class="size-5 opacity-80 group-hover:opacity-100" />
                                    <span class="sr-only">{{ link.title }}</span>
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ link.title }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
