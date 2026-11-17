<?php

namespace App\Exceptions;

use App\Enums\BranchOperatingMode;
use Exception;

class OperatingModeUnavailableException extends Exception
{
    public function __construct(public readonly BranchOperatingMode $operatingMode)
    {
        parent::__construct($operatingMode->displayName().' is not configured for the selected branch.');
    }
}