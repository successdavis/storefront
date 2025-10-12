<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type MainNavItem, NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, PackageCheck, Store  } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: MainNavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Products',
        icon: PackageCheck,
        subItems: [
            {
                title: 'All Products',
                href: '/admin/products',
                icon: ''
            },
            {
                title: 'Add New Product',
                href: '/admin/products/create',
                icon: ''
            },
            {
                title: 'Category',
                href: '/admin/categories',
                icon: ''
            },
            {
                title: 'Brand',
                href: '/admin/brands',
                icon: ''
            },
            {
                title: 'Attribute',
                href: '/admin/variant-types',
                icon: ''
            },
        ]
    },
    {
        title: 'Purchase Orders',
        icon: Store,
        subItems: [
            {
                title: 'Create PO',
                href: '/admin/purchase-order',
                icon: ''
            },
            {
                title: 'PO List',
                href: '/admin/purchase-orders/index',
                icon: ''
            },
        ]
    },
    {
        title: 'Inventory',
        icon: Store,
        subItems: [
            {
                title: 'Stock Entries',
                href: '/admin/stock-entries',
                icon: ''
            },
            {
                title: 'Create Adjustment',
                href: '/admin/stock-adjustments/create',
                icon: ''
            },
            {
                title: 'Adjustment',
                href: '/admin/stock-adjustments',
                icon: ''
            },
            {
                title: 'Stock Returns',
                href: '/admin/stock-returns',
                icon: ''
            },
        ]
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
