<?php

namespace App\Enums;

/**
 * S-16: canonical list of Spatie permission strings used across the app.
 *
 * Before this class, every call site hard-coded the permission string
 * (e.g. `$user->can('delete records')`). A typo silently failed closed
 * — any `can('delete recrods')` returned `false` without ever warning
 * the developer. Using constants forces the typo to become a PHP
 * "undefined constant" error at compile time.
 *
 * The string VALUES must match what RolesAndPermissionsSeeder creates.
 * If you add a new permission here, update the seeder in the same PR.
 */
final class Permission
{
    // Module access — also used by CheckModuleAccess middleware via
    // `permission:<value>`.
    public const ACCESS_DASHBOARD = 'access dashboard';

    public const ACCESS_CHECKIN = 'access check-in';

    public const ACCESS_APPOINTMENTS = 'access appointments';

    public const ACCESS_FINANCE = 'access finance';

    public const ACCESS_INVENTORY = 'access inventory';

    public const ACCESS_CUSTOMERS = 'access customers';

    public const ACCESS_MANAGEMENT = 'access management';

    public const ACCESS_TIRE_HOTEL = 'access tire-hotel';

    // Cross-resource visibility.
    public const VIEW_ALL_APPOINTMENTS = 'view all appointments';

    public const VIEW_ALL_FINANCE = 'view all finance';

    public const VIEW_ALL_WORK_ORDERS = 'view all work-orders';

    // Elevated actions — typically admin-only.
    public const MANAGE_USERS = 'manage users';

    public const MANAGE_SETTINGS = 'manage settings';

    public const DELETE_RECORDS = 'delete records';

    /**
     * All permission strings as a flat array — handy for seeders and
     * syncing Spatie role/permission rows.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ACCESS_DASHBOARD,
            self::ACCESS_CHECKIN,
            self::ACCESS_APPOINTMENTS,
            self::ACCESS_FINANCE,
            self::ACCESS_INVENTORY,
            self::ACCESS_CUSTOMERS,
            self::ACCESS_MANAGEMENT,
            self::ACCESS_TIRE_HOTEL,
            self::VIEW_ALL_APPOINTMENTS,
            self::VIEW_ALL_FINANCE,
            self::VIEW_ALL_WORK_ORDERS,
            self::MANAGE_USERS,
            self::MANAGE_SETTINGS,
            self::DELETE_RECORDS,
        ];
    }
}
