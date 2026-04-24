<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Tenant-owned data exports (customer CSV + JSON backup). Both are admin-
 * only and run inside the tenant's scope — no cross-tenant rows because
 * the Eloquent queries here do NOT opt out of the global tenant scope.
 */
class ExportController extends Controller
{
    /**
     * Whitelist of fields per model that are safe to include in a tenant-side backup.
     *
     * CRITICAL SECURITY NOTE: the previous implementation used $record->toJson(),
     * which serialised every fillable field. That included invite_token on User
     * (password is already hidden), the free-form `data` blob on audit_logs (which
     * may contain before/after snapshots of any column, including password hashes
     * on user update events), and any future sensitive field added to a fillable.
     *
     * This list is an explicit allow-list. To add a field, you must list it here.
     * Models not listed (User, TenantApiToken, AuditLog) are intentionally excluded
     * from the tenant-downloadable backup.
     */
    private const BACKUP_SAFE_FIELDS = [
        \App\Models\Customer::class => [
            'id', 'name', 'email', 'phone', 'address', 'city', 'postal_code',
            'notes', 'is_active', 'created_at', 'updated_at',
        ],
        \App\Models\Vehicle::class => [
            'id', 'customer_id', 'license_plate', 'make', 'model', 'year',
            'color', 'mileage', 'vin', 'created_at', 'updated_at',
        ],
        \App\Models\Checkin::class => [
            'id', 'customer_id', 'vehicle_id', 'service_type', 'service_description',
            'priority', 'status', 'checkin_time', 'checkout_time', 'estimated_cost',
            'actual_cost', 'technician_notes', 'created_at', 'updated_at',
        ],
        \App\Models\WorkOrder::class => [
            'id', 'checkin_id', 'customer_id', 'vehicle_id', 'technician_id',
            'status', 'service_tasks', 'customer_issues', 'technician_notes',
            'parts_used', 'started_at', 'completed_at', 'scheduled_at',
            'estimated_minutes', 'service_bay', 'created_at', 'updated_at',
        ],
        \App\Models\Invoice::class => [
            'id', 'invoice_number', 'work_order_id', 'customer_id', 'vehicle_id',
            'status', 'issue_date', 'due_date', 'subtotal', 'tax_total',
            'discount_total', 'total', 'paid_amount', 'notes',
            'created_at', 'updated_at',
        ],
        \App\Models\Payment::class => [
            'id', 'invoice_id', 'amount', 'method', 'payment_date',
            'transaction_reference', 'notes', 'created_at',
            // NOTE: idempotency_key is intentionally excluded — it is an internal
            // implementation detail and may encode user_id in derived form.
        ],
        \App\Models\Product::class => [
            'id', 'name', 'description', 'sku', 'price', 'cost', 'stock_quantity',
            'min_stock_quantity', 'unit', 'is_active', 'category',
            'created_at', 'updated_at',
        ],
        \App\Models\Service::class => [
            'id', 'name', 'description', 'code', 'price', 'duration_minutes',
            'category', 'is_active', 'created_at', 'updated_at',
        ],
        \App\Models\Tire::class => [
            'id', 'customer_id', 'vehicle_id', 'brand', 'model', 'size', 'season',
            'quantity', 'condition', 'storage_location', 'storage_date',
            'status', 'notes', 'created_at', 'updated_at',
        ],
        \App\Models\Appointment::class => [
            'id', 'customer_id', 'vehicle_id', 'title', 'start_time', 'end_time',
            'status', 'type', 'notes', 'created_at', 'updated_at',
        ],
    ];

    /**
     * S-14: fields whose values must be masked if the operator asks for a
     * "redacted" export. Customer contact details are PII — useful for the
     * tenant but risky if the backup file is shared with third parties
     * (bookkeepers, new staff, migration vendors). The redacted export keeps
     * the record structure so data relationships still make sense.
     */
    private const BACKUP_PII_FIELDS = [
        \App\Models\Customer::class => ['email', 'phone', 'address', 'city', 'postal_code', 'notes'],
    ];

    public function customers()
    {
        Gate::authorize('perform-admin-actions');

        $customers = \App\Models\Customer::all();
        $filename = 'crm-customers-'.date('Y-m-d').'.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($customers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Email', 'Phone', 'Created At']);

            foreach ($customers as $customer) {
                fputcsv($file, array_map([self::class, 'neutralizeCsvCell'], [
                    $customer->id,
                    $customer->name,
                    $customer->email,
                    $customer->phone,
                    (string) $customer->created_at,
                ]));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function backup(Request $request)
    {
        Gate::authorize('perform-admin-actions');

        $filename = 'crm-backup-'.now()->format('Y-m-d-His').'.json';
        $safeFields = self::BACKUP_SAFE_FIELDS;
        $redactPii = $request->boolean('redact_pii');
        $piiFields = self::BACKUP_PII_FIELDS;

        return response()->streamDownload(function () use ($safeFields, $redactPii, $piiFields) {
            echo '{"metadata":'.json_encode([
                'generated_at' => now()->toIso8601String(),
                'version' => '2.0',
                'app_name' => config('app.name'),
                'redacted' => $redactPii,
                'note' => 'This backup contains business data only. '
                    .'User accounts, auth tokens, and audit logs are excluded by design.'
                    .($redactPii ? ' Customer PII has been masked.' : ''),
            ]);

            foreach ($safeFields as $modelClass => $fields) {
                $key = Str::snake(class_basename($modelClass)).'s';
                $fieldsToRedact = $redactPii ? ($piiFields[$modelClass] ?? []) : [];

                echo ',"'.$key.'":[';
                $first = true;
                foreach ($modelClass::cursor() as $record) {
                    if (! $first) {
                        echo ',';
                    }
                    $row = $record->only($fields);

                    // Replace each PII field with a structural placeholder so
                    // the record shape stays usable for restore testing but
                    // the sensitive value is gone.
                    foreach ($fieldsToRedact as $piiField) {
                        if (array_key_exists($piiField, $row) && $row[$piiField] !== null) {
                            $row[$piiField] = '[redacted]';
                        }
                    }

                    echo json_encode($row);
                    $first = false;
                }
                echo ']';
            }

            echo '}';
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /**
     * Neutralize CSV formula injection. Excel, LibreOffice Calc, and Google
     * Sheets evaluate a cell that begins with `=`, `+`, `-`, `@`, tab, or
     * carriage return as a formula — which means a customer whose name the
     * system records as `=HYPERLINK(...)` can run arbitrary expressions on
     * whoever opens the export. Prefixing a single quote neutralises this
     * without changing the displayed value meaningfully.
     *
     * Applied to every customer-sourced cell in the export. IDs and system
     * timestamps go through too, which is cheap and keeps the code
     * consistent.
     *
     * @param  mixed  $value
     */
    protected static function neutralizeCsvCell($value): string
    {
        $string = (string) ($value ?? '');
        if ($string === '') {
            return $string;
        }

        $firstChar = $string[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$string;
        }

        return $string;
    }
}
