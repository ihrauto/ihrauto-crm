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
        'view' => 'View',
        'status' => 'Status',
        'actions' => 'Actions',
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
        'subtotal' => 'Subtotal',
        'tax' => 'VAT',
        'discount' => 'Discount',
        'due_date' => 'Due date',
        'issue_date' => 'Issue date',
        'balance_due' => 'Balance due',
        'bill_to' => 'Bill to',
        'print_save_pdf' => 'Print / Save as PDF',
    ],

    'quote' => [
        'title' => 'Quote',
        'quotes' => 'Quotes',
        'new' => 'New quote',
        'number' => 'Number',
        'customer' => 'Customer',
        'issued' => 'Issued',
        'expires' => 'Expires',
        'status_draft' => 'Draft',
        'status_sent' => 'Sent',
        'status_accepted' => 'Accepted',
        'status_rejected' => 'Rejected',
        'status_converted' => 'Converted',
        'convert_to_invoice' => 'Convert to invoice',
        'line_items' => 'Line items',
        'add_item' => '+ Add item',
        'description' => 'Description',
        'quantity' => 'Qty',
        'unit_price' => 'Unit',
        'vat_rate' => 'VAT %',
        'line_total' => 'Line total',
        'empty_list' => 'No quotes yet.',
        'all_statuses' => 'All statuses',
        'search_placeholder' => 'Search by number or customer',
        'save_changes' => 'Save changes',
        'filter' => 'Filter',
        'view_invoice' => 'View invoice :number',
    ],

    'errors' => [
        'access_denied' => 'Access denied',
        'not_found' => 'Not found',
        'server_error' => 'Something went wrong on our end.',
        'session_expired' => 'Your session has expired. Please sign in again.',
    ],

];
