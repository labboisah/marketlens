<?php

namespace App\Enums;

enum BranchOperatingMode: string
{
    case Sales = 'sales';
    case Production = 'production';
    case Services = 'services';

    public function displayName(): string
    {
        return match ($this) {
            self::Sales => 'Sales',
            self::Production => 'Production',
            self::Services => 'Services',
        };
    }
}