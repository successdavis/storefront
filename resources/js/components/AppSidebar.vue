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
    Truck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

type SidebarSubItem = {
    title: string;
    href: string;
    icon?: MainNavItem['icon'];
    isActive?: boolean;
    condition?: string;
};

type SidebarNavItem = Omit<MainNavItem, 'subItems'> & {
    condition?: string;
    subItems?: SidebarSubItem[];
};

const page = usePage();

const primaryRole = computed(() => page.props.auth?.primary_role ?? page.props.auth?.user?.primary_role ?? 'customer');
const capabilities = computed<Record<string, boolean>>(() => page.props.auth?.capabilities ?? page.props.auth?.user?.capabilities ?? {});

const adminNavItems: SidebarNavItem[] = [
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
        title: 'Analytics',
        icon: BookOpen,
        subItems: [
            { title: 'Overview', href: '/admin/analytics' },
            {
                title: 'Settings',
                href: '/admin/analytics/settings',
                condition: 'can_manage_analytics',
            },
        ],
        condition: 'can_view_analytics',
    },
    {
        title: 'Accounting',
        icon: ReceiptText,
        subItems: [
            { title: 'Overview', href: '/admin/accounting', condition: 'can_view_accounting' },
            { title: 'Chart of Accounts', href: '/admin/accounting/accounts', condition: 'can_manage_accounting' },
            { title: 'Journal Entries', href: '/admin/accounting/journal-entries', condition: 'can_view_accounting' },
            { title: 'Gateway Settlements', href: '/admin/accounting/gateway-settlements', condition: 'can_manage_accounting' },
            { title: 'Expenses', href: '/admin/accounting/expenses', condition: 'can_manage_accounting_expenses' },
            { title: 'General Ledger', href: '/admin/accounting/reports/ledger', condition: 'can_view_accounting_reports' },
            { title: 'Trial Balance', href: '/admin/accounting/reports/trial-balance', condition: 'can_view_accounting_reports' },
            { title: 'Profit & Loss', href: '/admin/accounting/reports/profit-loss', condition: 'can_view_accounting_reports' },
            { title: 'Balance Sheet', href: '/admin/accounting/reports/balance-sheet', condition: 'can_view_accounting_reports' },
        ],
        condition: 'can_view_accounting',
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
            { title: 'Discounts', href: '/admin/discounts' },
            { title: 'Coupons', href: '/admin/coupons' },
            { title: 'Category Price List', href: '/admin/reports/category-price-list' },
        ],
    },
    {
        title: 'Shipping',
        icon: Truck,
        subItems: [
            { title: 'Shipping Methods', href: '/admin/shipping-methods' },
            { title: 'Shipping Rates', href: '/admin/shipping-rates' },
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
        title: 'Orders',
        icon: ReceiptText,
        href: '/admin/orders',
    },
    {
        title: 'Customers',
        icon: Users,
        href: '/admin/customers',
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
    {
        title: 'Inventory Audit',
        icon: Boxes,
        subItems: [
            { title: 'Stock Audit', href: '/sales/inventory/stock-audit' },
            { title: 'Mobile Audit', href: '/sales/inventory/stock-audit/mobile' },
        ],
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
        return adminNavItems
            .filter((item) => !item.condition || capabilities.value[item.condition] !== false)
            .map(({ condition, subItems, ...item }) => ({
                ...item,
                subItems: subItems
                    ?.filter((subItem) => !subItem.condition || capabilities.value[subItem.condition] !== false)
                    .map(({ condition: subCondition, ...subItem }) => subItem),
            }));
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

        <SidebarContent class="admin-sidebar-scroll">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="footerNavItems.length" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

