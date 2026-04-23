<?php

/**
 * Core CRM UI strings.
 *
 * This file is the foundation for future i18n work. It contains the most
 * frequently-displayed strings that were previously hardcoded in blade
 * templates. Adding new languages (German, French, Italian for the Swiss
 * market) is a matter of copying this file to lang/de/crm.php, lang/fr/crm.php,
 * etc., and translating the values.
 *
 * Keys are namespaced by UI area so they're discoverable.
 */
return [

    'common' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'create' => 'Create',
        'update' => 'Update',
        'search' => 'Search',
        'close' => 'Close',
        'back' => 'Back',
        'confirm' => 'Confirm',
        'loading' => 'Loading...',
        'no_results' => 'No results found.',
        'required' => 'Required',
        'optional' => 'Optional',
    ],

    'status' => [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'scheduled' => 'Scheduled',
        'busy' => 'Busy',
        'available' => 'Available',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'work_order' => [
        'title' => 'Work Order',
        'status_created' => 'Created',
        'status_scheduled' => 'Scheduled',
        'status_in_progress' => 'In Progress',
        'status_waiting_parts' => 'Waiting for Parts',
        'status_completed' => 'Completed',
        'status_invoiced' => 'Invoiced',
        'status_cancelled' => 'Cancelled',
        'technician' => 'Technician',
        'technician_busy' => 'Technician is busy',
        'technician_available' => 'Technician is available',
    ],

    'customer' => [
        'title' => 'Customer',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'address' => 'Address',
    ],

    'finance' => [
        'invoice' => 'Invoice',
        'payment' => 'Payment',
        'total' => 'Total',
        'paid' => 'Paid',
        'balance' => 'Balance',
        'overdue' => 'Overdue',
        'currency' => 'CHF',
    ],

    'errors' => [
        'access_denied' => 'Access denied',
        'not_found' => 'Not found',
        'server_error' => 'Something went wrong on our end.',
        'session_expired' => 'Your session has expired. Please sign in again.',
    ],

];
