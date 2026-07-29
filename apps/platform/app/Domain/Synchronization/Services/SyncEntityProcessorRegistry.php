<?php

namespace App\Domain\Synchronization\Services;

use App\Domain\Synchronization\Services\EntityProcessors\BranchPerformanceSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\CashierSettlementProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\CashierShiftProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\CustomerDebtSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\DailySalesSummaryProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\ExpenseProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\OperationalUserProfileProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SaleItemProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SalePaymentProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SaleProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\SecurityAlertProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\StockAlertProcessor;
use App\Domain\Synchronization\Services\EntityProcessors\StockSummaryProcessor;
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
        'customer_debt_summary' => CustomerDebtSummaryProcessor::class,
        'cashier_shift' => CashierShiftProcessor::class,
        'cashier_settlement' => CashierSettlementProcessor::class,
        'stock_summary' => StockSummaryProcessor::class,
        'stock_alert' => StockAlertProcessor::class,
        'security_alert' => SecurityAlertProcessor::class,
        'daily_sales_summary' => DailySalesSummaryProcessor::class,
        'branch_performance_summary' => BranchPerformanceSummaryProcessor::class,
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
