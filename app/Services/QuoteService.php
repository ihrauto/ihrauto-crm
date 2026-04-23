<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteSequence;
use Illuminate\Support\Facades\DB;

/**
 * Handles quote numbering, creation, updates, and status transitions so
 * the controller stays thin. Quote numbers are year-scoped per tenant:
 * `QT-YYYY-####`.
 */
class QuoteService
{
    /**
     * Legal quote status transitions. Anything else is rejected.
     */
    public const ALLOWED_TRANSITIONS = [
        'draft' => ['sent', 'rejected'],
        'sent' => ['accepted', 'rejected', 'draft'],
        'accepted' => ['rejected', 'converted'],
        'rejected' => [],
        'converted' => [],
    ];

    /**
     * Generate the next quote number for the tenant/year using a locked
     * sequence row — same pattern as InvoiceService::generateInvoiceNumber
     * so concurrent creates can't collide or skip numbers.
     */
    public function generateQuoteNumber(int $tenantId): string
    {
        $prefix = config('crm.quote.prefix', 'QT');
        $padding = (int) config('crm.quote.number_padding', 4);
        $year = now()->year;

        $sequence = QuoteSequence::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            try {
                $sequence = QuoteSequence::create([
                    'tenant_id' => $tenantId,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            } catch (\Throwable) {
                $sequence = QuoteSequence::query()
                    ->where('tenant_id', $tenantId)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $sequence->last_number++;
        $sequence->save();

        return $prefix.'-'.$year.'-'.str_pad(
            (string) $sequence->last_number, $padding, '0', STR_PAD_LEFT
        );
    }

    public function create(array $data, Customer $customer): Quote
    {
        return DB::transaction(function () use ($data, $customer) {
            $tenantId = $customer->tenant_id;
            $taxRate = $customer->tenant?->taxRate() ?? (float) config('crm.tax_rate', 8.1);

            $quote = Quote::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'quote_number' => $this->generateQuoteNumber($tenantId),
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $qty = (int) $item['quantity'];
                $unit = (float) $item['unit_price'];
                $quote->items()->create([
                    'tenant_id' => $tenantId,
                    'description' => $item['description'],
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'tax_rate' => $item['tax_rate'] ?? $taxRate,
                    'total' => round($qty * $unit, 2),
                ]);
            }

            $this->recalculateTotals($quote);

            return $quote->fresh('items');
        });
    }

    public function update(Quote $quote, array $data): Quote
    {
        if (! in_array($quote->status, ['draft', 'sent'], true)) {
            throw new \InvalidArgumentException(
                "Quote #{$quote->quote_number} cannot be edited once it is {$quote->status}."
            );
        }

        return DB::transaction(function () use ($quote, $data) {
            $quote->fill(array_intersect_key($data, array_flip([
                'customer_id', 'vehicle_id', 'issue_date', 'expiry_date', 'notes',
            ])))->save();

            if (isset($data['items'])) {
                $quote->items()->delete();

                foreach ($data['items'] as $item) {
                    $qty = (int) $item['quantity'];
                    $unit = (float) $item['unit_price'];
                    $quote->items()->create([
                        'tenant_id' => $quote->tenant_id,
                        'description' => $item['description'],
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'tax_rate' => $item['tax_rate']
                            ?? $quote->tenant?->taxRate()
                            ?? (float) config('crm.tax_rate', 8.1),
                        'total' => round($qty * $unit, 2),
                    ]);
                }
            }

            if (isset($data['status']) && $data['status'] !== $quote->status) {
                $error = $this->validateStatusTransition($quote->status, $data['status']);
                if ($error) {
                    throw new \InvalidArgumentException($error);
                }
                $quote->status = $data['status'];
                $quote->save();
            }

            $this->recalculateTotals($quote);

            return $quote->fresh('items');
        });
    }

    public function validateStatusTransition(string $from, string $to): ?string
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            return "Cannot change quote status from '{$from}' to '{$to}'.";
        }

        return null;
    }

    public function recalculateTotals(Quote $quote): Quote
    {
        $quote->loadMissing('items');

        $subtotal = 0;
        $taxTotal = 0;

        foreach ($quote->items as $item) {
            $lineTotal = round((float) $item->quantity * (float) $item->unit_price, 2);
            if ((float) $item->total !== $lineTotal) {
                $item->total = $lineTotal;
                $item->save();
            }
            $subtotal += $lineTotal;
            $taxTotal += $lineTotal * ((float) $item->tax_rate / 100);
        }

        $quote->subtotal = round($subtotal, 2);
        $quote->tax_total = round($taxTotal, 2);
        $quote->total = round($subtotal + $taxTotal - ((float) ($quote->discount_total ?? 0)), 2);
        $quote->save();

        return $quote;
    }
}
