<?php

namespace App\Domain\Synchronization\Services;

use App\Domain\Synchronization\Services\EntityProcessors\BranchPerformanceSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\CashierSettlementProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\CashierShiftProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\CustomerDebtSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\DailySalesSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\ExpenseProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\OperationalRecordProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\OperationalUserProfileProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\PurchaseItemProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\PurchasePaymentProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\PurchaseProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SaleItemProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SalePaymentProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SaleProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SecurityAlertProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\StaffActivitySummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\StockAlertProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\StockMovementProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\StockSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SupplierBalanceSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SyncEntityProcessor;

class SyncEntityProcessorRegistry
{
    /** @var array<string, class-string<SyncEntityProcessor>> */
    private array $processors = [
        'operational_user_profile' => OperationalUserProfileProcessor::class,
        'sale' => SaleProcessor::class,
        'sale_item' => SaleItemProcessor::class,
        'sale_payment' => SalePaymentProcessor::class,
        'expense' => ExpenseProcessor::class,
        'purchase' => PurchaseProcessor::class,
        'purchase_item' => PurchaseItemProcessor::class,
        'purchase_payment' => PurchasePaymentProcessor::class,
        'supplier_balance_summary' => SupplierBalanceSummaryProcessor::class,
        'customer_debt_summary' => CustomerDebtSummaryProcessor::class,
        'cashier_shift' => CashierShiftProcessor::class,
        'cashier_settlement' => CashierSettlementProcessor::class,
        'staff_activity_summary' => StaffActivitySummaryProcessor::class,
        'stock_summary' => StockSummaryProcessor::class,
        'stock_alert' => StockAlertProcessor::class,
        'stock_movement' => StockMovementProcessor::class,
        'security_alert' => SecurityAlertProcessor::class,
        'daily_sales_summary' => DailySalesSummaryProcessor::class,
        'branch_performance_summary' => BranchPerformanceSummaryProcessor::class,
        'product' => OperationalRecordProcessor::class,
        'service_category' => OperationalRecordProcessor::class,
        'service' => OperationalRecordProcessor::class,
        'service_price' => OperationalRecordProcessor::class,
        'sale_stock_allocation' => OperationalRecordProcessor::class,
        'service_order_item' => OperationalRecordProcessor::class,
        'service_order' => OperationalRecordProcessor::class,
        'service_payment' => OperationalRecordProcessor::class,
        'service_revenue_allocation' => OperationalRecordProcessor::class,
        'service_revenue_share_rule' => OperationalRecordProcessor::class,
        'service_staff_assignment' => OperationalRecordProcessor::class,
        'service_work_bill_receipt' => OperationalRecordProcessor::class,
        'service_workstation_receipt' => OperationalRecordProcessor::class,
        'service_workstation_request' => OperationalRecordProcessor::class,
        'share_class' => OperationalRecordProcessor::class,
        'share_ownership_transaction' => OperationalRecordProcessor::class,
        'shareholder' => OperationalRecordProcessor::class,
        'staff_earning_transaction' => OperationalRecordProcessor::class,
        'stock_reconciliation' => OperationalRecordProcessor::class,
        'inventory_stock' => OperationalRecordProcessor::class,
        'supplier' => OperationalRecordProcessor::class,
        'workstation_bill' => OperationalRecordProcessor::class,
        'workstation_checkout' => OperationalRecordProcessor::class,
    ];

    public function supports(?string $entity): bool
    {
        return is_string($entity) && array_key_exists($entity, $this->processors);
    }

    public function processorFor(string $entity): SyncEntityProcessor
    {
        return app($this->processors[$entity]);
    }

    /**
     * @return list<string>
     */
    public function supportedEntities(): array
    {
        return array_keys($this->processors);
    }
}
