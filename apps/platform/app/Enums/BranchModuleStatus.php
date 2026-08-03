<?php

namespace App\Enums;

enum BranchModuleStatus: string
{
    case Inherit = 'inherit';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
