<?php

namespace App\Exceptions;

use App\Domain\Modules\Data\ModuleAccessResult;
use Exception;

class ModuleUnavailableException extends Exception
{
    public function __construct(public readonly ModuleAccessResult $access)
    {
        parent::__construct($access->module->displayName().' is not available for the selected business or branch.');
    }
}
