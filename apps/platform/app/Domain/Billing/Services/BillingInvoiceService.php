<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\BillingAccount;
use App\Domain\Billing\Models\BillingAccountAdjustment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Billing\Models\InvoicePayment;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Business;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillingInvoiceService
{
    public function ensureBillingAccount(Business $business): BillingAccount
    {
        return BillingAccount::query()->firstOrCreate([
            'business_id' => $business->id,
        ], [
            'uuid' => (string) Str::uuid(),
            'balance_minor' => 0,
            'currency' => $business->default_currency ?? config('marketlens.currency.default', 'NGN'),
            'status' => 'active',
        ]);
    }

    public function createInvoiceForSubscription(Business $business, Subscription $subscription, string $description, array $items): Invoice
    {
        $business->loadMissing('billingProfile');
        $plan = $subscription->plan;

        if (! $plan) {
            throw ValidationException::withMessages(['subscription' => 'Subscription must be attached to an active billing plan.']);
        }

        $invoice = DB::transaction(function () use ($business, $subscription, $description, $items): Invoice {
            $invoice = Invoice::query()->create([
                'business_id' => $business->id,
                'subscription_id' => $subscription->id,
                'uuid' => (string) Str::uuid(),
                'invoice_number' => $this->generateInvoiceNumber(),
                'status' => InvoiceStatus::Issued->value,
                'currency' => $business->default_currency ?? config('marketlens.currency.default', 'NGN'),
                'subtotal_minor' => 0,
                'total_minor' => 0,
                'description' => $description,
                'issued_at' => now(),
                'due_at' => now()->addDays((int) config('marketlens.billing.invoice_due_days', 7)),
                'metadata' => [],
            ]);

            $subtotal = 0;

            foreach ($items as $item) {
                $validated = Validator::validate($item, [
                    'description' => ['required', 'string', 'max:255'],
                    'quantity' => ['required', 'integer', 'min:1'],
                    'unit_price_minor' => ['required', 'integer', 'min:0'],
                    'metadata' => ['nullable', 'array'],
                ]);

                $validated['total_minor'] = $validated['quantity'] * $validated['unit_price_minor'];
                $subtotal += $validated['total_minor'];

                $invoice->items()->create([
                    'uuid' => (string) Str::uuid(),
                    'description' => $validated['description'],
                    'quantity' => $validated['quantity'],
                    'unit_price_minor' => $validated['unit_price_minor'],
                    'total_minor' => $validated['total_minor'],
                    'metadata' => $validated['metadata'] ?? [],
                ]);
            }

            $invoice->forceFill([
                'subtotal_minor' => $subtotal,
                'total_minor' => $subtotal,
            ])->save();

            return $invoice;
        });

        return $invoice;
    }

    public function recordInvoicePayment(Invoice $invoice, int $amountMinor, string $method, ?string $reference = null, ?Carbon $paidAt = null): InvoicePayment
    {
        if ($invoice->status !== InvoiceStatus::Issued) {
            throw ValidationException::withMessages(['invoice' => 'Only issued invoices can accept payments.']);
        }

        $paidAt = $paidAt ?? now();

        return DB::transaction(function () use ($invoice, $amountMinor, $method, $reference, $paidAt): InvoicePayment {
            $payment = $invoice->payments()->create([
                'uuid' => (string) Str::uuid(),
                'amount_minor' => $amountMinor,
                'currency' => $invoice->currency,
                'status' => PaymentStatus::Paid->value,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'metadata' => [],
            ]);

            $invoiceTotal = (int) $invoice->getRawOriginal('total_minor');
            $paidTotal = (int) $invoice->payments()->where('status', PaymentStatus::Paid->value)->sum('amount_minor');

            if ($paidTotal >= $invoiceTotal) {
                $invoice->forceFill(['status' => InvoiceStatus::Paid->value, 'paid_at' => $paidAt])->save();

                if ($invoice->subscription) {
                    $this->activateSubscriptionFromInvoice($invoice->subscription);
                }
            }

            $account = $this->ensureBillingAccount($invoice->business);
            $adjustmentType = $amountMinor >= 0 ? 'payment' : 'refund';

            $account->adjustments()->create([
                'uuid' => (string) Str::uuid(),
                'type' => $adjustmentType,
                'amount_minor' => $amountMinor,
                'currency' => $invoice->currency,
                'description' => 'Invoice payment recorded for '.$invoice->invoice_number,
                'metadata' => ['invoice_id' => $invoice->id],
                'recorded_at' => $paidAt,
            ]);

            $currentBalanceMinor = (int) $account->getRawOriginal('balance_minor');
            $account->forceFill(['balance_minor' => $currentBalanceMinor + $amountMinor])->save();

            return $payment;
        });
    }

    private function activateSubscriptionFromInvoice(Subscription $subscription): void
    {
        if ($subscription->status !== 'pending') {
            return;
        }

        $plan = $subscription->plan;

        $startsAt = $subscription->starts_at ?? now();
        $endsAt = $this->calculateSubscriptionEndDate($plan, $startsAt);

        $subscription->forceFill([
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ])->save();
    }

    private function calculateSubscriptionEndDate(Plan $plan, Carbon $startsAt): Carbon
    {
        return match ($plan->billing_interval) {
            'annual' => $startsAt->copy()->addYear(),
            'monthly' => $startsAt->copy()->addMonth(),
            default => $startsAt->copy()->addMonth(),
        };
    }

    public function generateInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    public function createPendingSubscription(Business $business, Plan $plan): Subscription
    {
        return Subscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'starts_at' => now(),
            'ends_at' => null,
        ]);
    }
}
