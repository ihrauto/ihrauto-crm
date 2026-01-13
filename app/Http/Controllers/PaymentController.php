<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Store a newly created payment in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'payment_date' => 'required|date',
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        DB::beginTransaction();

        try {
            // Idempotency: Check for duplicate submission using idempotency key or transaction reference
            $idempotencyKey = $validated['idempotency_key'] ?? $validated['transaction_reference'] ?? null;
            if ($idempotencyKey) {
                $existingPayment = Payment::where('transaction_reference', $idempotencyKey)
                    ->where('invoice_id', $validated['invoice_id'])
                    ->first();

                if ($existingPayment) {
                    DB::rollBack();
                    return redirect()->route('invoices.show', $validated['invoice_id'])
                        ->with('info', 'Payment already recorded (duplicate submission prevented).');
                }
            }

            $invoice = Invoice::findOrFail($validated['invoice_id']);

            // Security: Verify tenant ownership (prevent IDOR)
            if ($invoice->tenant_id !== auth()->user()->tenant_id) {
                DB::rollBack();
                abort(403, 'Unauthorized: Invoice does not belong to your organization.');
            }

            // Security Check: Status
            if (in_array($invoice->status, ['paid', 'cancelled'])) {
                DB::rollBack();

                return back()->with('error', 'Cannot process payment. Invoice is already ' . $invoice->status . '.');
            }

            // Security Check: Overpayment
            if ($validated['amount'] > $invoice->balance) {
                DB::rollBack();

                return back()->with('error', 'Payment amount (' . number_format($validated['amount'], 2) . ') exceeds remaining balance (' . number_format($invoice->balance, 2) . ').');
            }

            // Create Payment
            Payment::create([
                'tenant_id' => auth()->user()->tenant_id,
                'invoice_id' => $validated['invoice_id'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'payment_date' => $validated['payment_date'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Update Invoice Paid Amount
            $invoice->paid_amount += $validated['amount'];

            // Update Status Logic
            if ($invoice->paid_amount >= $invoice->total) {
                $invoice->status = 'paid';
                $invoice->locked_at = now();
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partial';
            } else {
                $invoice->status = 'unpaid';
            }

            $invoice->save();

            DB::commit();

            return redirect()->route('invoices.show', $invoice)->with('success', 'Payment registered successfully. Invoice marked as ' . strtoupper($invoice->status) . '.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error registering payment: ' . $e->getMessage());
        }
    }
}
