<?php

namespace App\Domain\Tenancy\Services;

class BranchContext
{
    private ?int $branchId = null;

    public function set(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function id(): ?int
    {
        return $this->branchId;
    }

    public function clear(): void
    {
        $this->branchId = null;
    }
}
