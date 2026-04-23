<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Store a newly created payment in storage.
     *
     * C-01: validation lives in StorePaymentRequest; this method only
     * orchestrates the idempotent write + invoice sync.
     */
    public function store(StorePaymentRequest $request)
    {
        Gate::authorize('view-financials');
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // Resolve or derive an idempotency key.
            //
            // CRITICAL: the previous implementation only checked for duplicates when
            // the client supplied a key. Without one, a browser back-button resubmit
            // would silently post the payment twice. We now derive a deterministic
            // fallback from the payment's distinguishing fields so repeated identical
            // submissions are blocked regardless of client behavior.
            $idempotencyKey = $validated['idempotency_key']
                ?? $validated['transaction_reference']
                ?? hash('sha256', implode('|', [
                    'payment',
                    (string) $validated['invoice_id'],
                    number_format((float) $validated['amount'], 2, '.', ''),
                    $validated['payment_date'],
                    (string) $validated['method'],
                    (string) (auth()->id() ?? 'anon'),
                ]));

            $existingPayment = Payment::where('idempotency_key', $idempotencyKey)
                ->where('invoice_id', $validated['invoice_id'])
                ->first();

            if ($existingPayment) {
                DB::rollBack();

                return redirect()->route('invoices.show', $validated['invoice_id'])
                    ->with('info', 'Payment already recorded (duplicate submission prevented).');
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($validated['invoice_id']);

            // Security Check: Status
            if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_PAID, Invoice::STATUS_VOID], true)) {
                DB::rollBack();

                return back()->with('error', 'Cannot process payment. Invoice is already '.$invoice->status.'.');
            }

            // Security Check: Overpayment
            if ($validated['amount'] > $invoice->balance) {
                DB::rollBack();

                return back()->with('error', 'Payment amount ('.number_format($validated['amount'], 2).') exceeds remaining balance ('.number_format($invoice->balance, 2).').');
            }

            // Create Payment
            Payment::create([
                'tenant_id' => tenant_id(),
                'invoice_id' => $validated['invoice_id'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'payment_date' => $validated['payment_date'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'notes' => $validated['notes'] ?? null,
            ]);

            $invoice = $this->invoiceService->syncPaymentState($invoice);

            DB::commit();

            return redirect()->route('invoices.show', $invoice)->with('success', 'Payment registered successfully. Invoice marked as '.strtoupper($invoice->status).'.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error registering payment: '.$e->getMessage());
        }
    }
}
