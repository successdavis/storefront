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
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Boxes,
    Heart,
    LayoutGrid,
    MapPin,
    Monitor,
    PackageCheck,
    ReceiptText,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Store,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();

const primaryRole = computed(() => page.props.auth?.primary_role ?? page.props.auth?.user?.primary_role ?? 'customer');
const capabilities = computed<Record<string, boolean>>(() => page.props.auth?.capabilities ?? page.props.auth?.user?.capabilities ?? {});

const adminNavItems: MainNavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin',
        icon: LayoutGrid,
    },
    {
        title: 'Sales Workspace',
        href: '/sales',
        icon: ShoppingBag,
    },
    {
        title: 'POS',
        href: '/admin/pos',
        icon: ShoppingCart,
    },
    {
        title: 'Products',
        icon: PackageCheck,
        subItems: [
            { title: 'All Products', href: '/admin/products' },
            { title: 'Add New Product', href: '/admin/products/create' },
            { title: 'Category', href: '/admin/categories' },
            { title: 'Brand', href: '/admin/brands' },
            { title: 'Variants', href: '/admin/variant-types' },
        ],
    },
    {
        title: 'Purchase Orders',
        icon: Store,
        subItems: [
            { title: 'Create PO', href: '/admin/purchase-order' },
            { title: 'PO List', href: '/admin/purchase-orders/index' },
        ],
    },
    {
        title: 'Inventory',
        icon: Boxes,
        subItems: [
            { title: 'Create Adjustment', href: '/admin/stock-adjustments/create' },
            { title: 'Adjustment', href: '/admin/stock-adjustments' },
            { title: 'Barcode Labels', href: '/admin/barcodes' },
            { title: 'Stock Audit', href: '/admin/inventory/stock-audit' },
            { title: 'Mobile Audit', href: '/admin/inventory/stock-audit/mobile' },
            { title: 'Discrepancies', href: '/admin/inventory/discrepancies' },
        ],
    },
    {
        title: 'Warehouse',
        icon: Building2,
        href: '/admin/warehouses',
    },
    {
        title: 'POS Terminals',
        icon: Monitor,
        href: '/admin/pos-terminals',
    },
    {
        title: 'Payments',
        icon: BookOpen,
        subItems: [
            { title: 'Recovery', href: '/admin/payment-recovery' },
            { title: 'Transactions', href: '/admin/transactions' },
        ],
    },
    {
        title: 'Staff',
        icon: Users,
        subItems: [
            { title: 'All Staff', href: '/admin/staff' },
        ],
    },
];

const salesNavItems: MainNavItem[] = [
    {
        title: 'Sales Dashboard',
        href: '/sales',
        icon: LayoutGrid,
    },
    {
        title: 'POS',
        href: '/sales/pos',
        icon: ShoppingCart,
    },
    {
        title: 'Orders',
        href: '/sales/orders',
        icon: ReceiptText,
    },
    {
        title: 'Customers',
        href: '/sales/customers',
        icon: Users,
    },
];

const customerNavItems: MainNavItem[] = [
    {
        title: 'Overview',
        href: '/account',
        icon: LayoutGrid,
    },
    {
        title: 'My Orders',
        href: '/account/orders',
        icon: ReceiptText,
    },
    {
        title: 'Wishlist',
        href: '/account/wishlist',
        icon: Heart,
    },
    {
        title: 'Saved for Later',
        href: '/account/saved-for-later',
        icon: ShoppingBag,
    },
    {
        title: 'Addresses',
        href: '/account/addresses',
        icon: MapPin,
    },
];

const mainNavItems = computed<MainNavItem[]>(() => {
    if (capabilities.value.can_access_admin) {
        return adminNavItems;
    }

    if (capabilities.value.can_access_sales && primaryRole.value === 'sales_representative') {
        return salesNavItems;
    }

    return customerNavItems;
});

const footerNavItems = computed<NavItem[]>(() => {
    if (capabilities.value.can_access_admin) {
        return [
            {
                title: 'Profile Settings',
                href: '/settings/profile',
                icon: Settings,
            },
        ];
    }

    if (primaryRole.value === 'customer') {
        return [
            {
                title: 'Account Settings',
                href: '/settings/profile',
                icon: Settings,
            },
        ];
    }

    return [];
});
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
            <NavFooter v-if="footerNavItems.length" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
