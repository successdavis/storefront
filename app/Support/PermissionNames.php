<?php

namespace App\Support;

final class PermissionNames
{
    public const ACCESS_ADMIN = 'admin.access';
    public const VIEW_ADMIN_DASHBOARD = 'admin.dashboard.view';
    public const VIEW_ADMIN_TRANSACTIONS = 'admin.transactions.view';
    public const MANAGE_ADMIN_PAYMENT_RECOVERY = 'admin.payment_recovery.manage';
    public const MANAGE_ADMIN_ORDERS = 'admin.orders.manage';
    public const MANAGE_ADMIN_CATALOG = 'admin.catalog.manage';
    public const MANAGE_ADMIN_INVENTORY = 'admin.inventory.manage';
    public const MANAGE_ADMIN_STAFF = 'admin.staff.manage';

    public const ACCESS_SALES = 'sales.access';
    public const VIEW_SALES_DASHBOARD = 'sales.dashboard.view';
    public const VIEW_SALES_ORDERS = 'sales.orders.view';
    public const VIEW_SALES_CUSTOMERS = 'sales.customers.view';
    public const CREATE_SALES_CUSTOMERS = 'sales.customers.create';
    public const USE_SALES_POS = 'sales.pos.use';

    public const ACCESS_ACCOUNT = 'account.access';
    public const VIEW_ACCOUNT_DASHBOARD = 'account.dashboard.view';
    public const VIEW_ACCOUNT_ORDERS = 'account.orders.view';
    public const MANAGE_ACCOUNT_ADDRESSES = 'account.addresses.manage';
    public const MANAGE_ACCOUNT_SAVED_ITEMS = 'account.saved_items.manage';
    public const USE_CHECKOUT = 'checkout.use';

    /**
     * @return list<string>
     */
    public static function admin(): array
    {
        return [
            self::ACCESS_ADMIN,
            self::VIEW_ADMIN_DASHBOARD,
            self::VIEW_ADMIN_TRANSACTIONS,
            self::MANAGE_ADMIN_PAYMENT_RECOVERY,
            self::MANAGE_ADMIN_ORDERS,
            self::MANAGE_ADMIN_CATALOG,
            self::MANAGE_ADMIN_INVENTORY,
            self::MANAGE_ADMIN_STAFF,
        ];
    }

    /**
     * @return list<string>
     */
    public static function sales(): array
    {
        return [
            self::ACCESS_SALES,
            self::VIEW_SALES_DASHBOARD,
            self::VIEW_SALES_ORDERS,
            self::VIEW_SALES_CUSTOMERS,
            self::CREATE_SALES_CUSTOMERS,
            self::USE_SALES_POS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function customer(): array
    {
        return [
            self::ACCESS_ACCOUNT,
            self::VIEW_ACCOUNT_DASHBOARD,
            self::VIEW_ACCOUNT_ORDERS,
            self::MANAGE_ACCOUNT_ADDRESSES,
            self::MANAGE_ACCOUNT_SAVED_ITEMS,
            self::USE_CHECKOUT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function director(): array
    {
        return array_values(array_unique([
            ...self::admin(),
            ...self::sales(),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique([
            ...self::admin(),
            ...self::sales(),
            ...self::customer(),
        ]));
    }
}
