<?php

use PHPUnit\Framework\TestCase;
use App\Domain\Services\Revenue\RevenueShareRuleResolver;

class RevenueShareResolverTest extends TestCase
{
    public function test_preview_allocation_empty_rule()
    {
        $resolver = new RevenueShareRuleResolver();
        $this->assertIsArray($resolver->previewAllocation(null, 100.0));
    }
}
