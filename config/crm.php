<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    |
    | Default tax rate for Switzerland (VAT/MwSt).
    |
    */
    'tax_rate' => env('CRM_TAX_RATE', 8.1),

    /*
    |--------------------------------------------------------------------------
    | Service Bays Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the workshop service bays.
    |
    */
    'service_bays' => [
        'count' => env('CRM_SERVICE_BAYS', 6),
        'names' => [
            'Bay 1',
            'Bay 2',
            'Bay 3',
            'Bay 4',
            'Bay 5',
            'Bay 6',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tire Hotel Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for tire storage operations.
    |
    */
    'tire_hotel' => [
        'default_storage_fee' => env('CRM_STORAGE_FEE', 40.00),
        'default_tire_change_fee' => env('CRM_TIRE_CHANGE_FEE', 50.00),
        'default_capacity' => env('CRM_TIRE_CAPACITY', 584),
        'section_capacity' => env('CRM_SECTION_CAPACITY', 20),
        'inspection_interval_months' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for invoice generation.
    |
    */
    'invoice' => [
        'prefix' => env('CRM_INVOICE_PREFIX', 'INV'),
        'default_due_days' => env('CRM_INVOICE_DUE_DAYS', 30),
        'number_padding' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Service Times
    |--------------------------------------------------------------------------
    |
    | Fallback values when no real data is available.
    |
    */
    'defaults' => [
        'average_service_time_hours' => 2.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'code' => env('CRM_CURRENCY', 'CHF'),
        'symbol' => env('CRM_CURRENCY_SYMBOL', 'CHF'),
        'decimal_places' => 2,
    ],

    'support' => [
        'email' => env('CRM_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@example.com')),
        'phone' => env('CRM_SUPPORT_PHONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendar / Appointment UI
    |--------------------------------------------------------------------------
    |
    | C-10: moved out of AppointmentController so designers can tweak the
    | colour palette without touching PHP. Keys match Appointment::status.
    */
    'appointment_status_colors' => [
        'scheduled' => '#6366f1',  // Indigo
        'confirmed' => '#8b5cf6',  // Purple
        'completed' => '#22c55e',  // Green
        'failed' => '#ef4444',     // Red
        'cancelled' => '#9ca3af',  // Gray
        'no_show' => '#6b7280',    // Gray (darker)
    ],
];
