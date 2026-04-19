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
    public const VIEW_ADMIN_ANALYTICS = 'admin.analytics.view';
    public const MANAGE_ADMIN_ANALYTICS = 'admin.analytics.manage';
    public const VIEW_ADMIN_CUSTOMERS = 'admin.customers.view';
    public const VIEW_ADMIN_CUSTOMER_DETAILS = 'admin.customers.view_details';
    public const UPDATE_ADMIN_CUSTOMERS = 'admin.customers.update';
    public const CHANGE_ADMIN_CUSTOMER_STATUS = 'admin.customers.suspend';
    public const EMAIL_ADMIN_CUSTOMERS = 'admin.customers.email';
    public const EXPORT_ADMIN_CUSTOMERS = 'admin.customers.export';
    public const MANAGE_ADMIN_CUSTOMER_NOTES = 'admin.customers.notes.manage';
    public const BULK_ADMIN_CUSTOMER_ACTIONS = 'admin.customers.bulk_actions';

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
            self::VIEW_ADMIN_ANALYTICS,
            self::MANAGE_ADMIN_ANALYTICS,
            self::VIEW_ADMIN_CUSTOMERS,
            self::VIEW_ADMIN_CUSTOMER_DETAILS,
            self::UPDATE_ADMIN_CUSTOMERS,
            self::CHANGE_ADMIN_CUSTOMER_STATUS,
            self::EMAIL_ADMIN_CUSTOMERS,
            self::EXPORT_ADMIN_CUSTOMERS,
            self::MANAGE_ADMIN_CUSTOMER_NOTES,
            self::BULK_ADMIN_CUSTOMER_ACTIONS,
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
