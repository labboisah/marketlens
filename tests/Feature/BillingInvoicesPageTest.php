<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoicePayment;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BusinessStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\SuperAdmin\BillingInvoicesPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders super admin billing invoices with formatted totals', function (): void {
    $superAdmin = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
        'is_super_admin' => true,
    ]);

    $business = Business::query()->create([
        'name' => 'Ada Stores',
        'status' => BusinessStatus::Active,
    ]);

    $plan = Plan::query()->create([
        'name' => 'Growth Plan',
        'slug' => 'growth-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 125000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $subscription = Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    Invoice::query()->create([
        'business_id' => $business->id,
        'subscription_id' => $subscription->id,
        'invoice_number' => 'INV-TEST-001',
        'status' => InvoiceStatus::Issued,
        'currency' => 'NGN',
        'subtotal_minor' => '1234.56',
        'total_minor' => '1234.56',
        'issued_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(BillingInvoicesPage::class)
        ->assertOk()
        ->assertSee('View')
        ->assertSee('Mark paid')
        ->assertSee('Cancel')
        ->assertSee('INV-TEST-001')
        ->assertSee('Ada Stores')
        ->assertSee('Growth Plan')
        ->assertSee('NGN 1,234.56');
});

it('lets super admins view mark paid and cancel issued invoices', function (): void {
    $superAdmin = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
        'is_super_admin' => true,
    ]);

    $business = Business::query()->create([
        'name' => 'Bala Stores',
        'status' => BusinessStatus::Active,
    ]);

    $plan = Plan::query()->create([
        'name' => 'Operator Plan',
        'slug' => 'operator-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 50000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $subscription = Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $payableInvoice = Invoice::query()->create([
        'business_id' => $business->id,
        'subscription_id' => $subscription->id,
        'invoice_number' => 'INV-PAY-001',
        'status' => InvoiceStatus::Issued,
        'currency' => 'NGN',
        'subtotal_minor' => '500.00',
        'total_minor' => '500.00',
        'issued_at' => now(),
    ]);

    $payableInvoice->items()->create([
        'description' => 'Monthly subscription',
        'quantity' => 1,
        'unit_price_minor' => '500.00',
        'total_minor' => '500.00',
    ]);

    $cancelledInvoice = Invoice::query()->create([
        'business_id' => $business->id,
        'subscription_id' => $subscription->id,
        'invoice_number' => 'INV-CANCEL-001',
        'status' => InvoiceStatus::Issued,
        'currency' => 'NGN',
        'subtotal_minor' => '250.00',
        'total_minor' => '250.00',
        'issued_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(BillingInvoicesPage::class)
        ->call('selectInvoice', $payableInvoice->id)
        ->assertSet('selectedInvoiceId', $payableInvoice->id)
        ->assertSee('Monthly subscription')
        ->assertSee('NGN 500.00')
        ->call('markPaid', $payableInvoice->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->call('cancelInvoice', $payableInvoice->id)
        ->assertHasErrors(['invoiceAction'])
        ->call('cancelInvoice', $cancelledInvoice->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($payableInvoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($cancelledInvoice->refresh()->status)->toBe(InvoiceStatus::Cancelled)
        ->and((int) InvoicePayment::query()->where('invoice_id', $payableInvoice->id)->value('amount_minor'))->toBe(50000);
});
