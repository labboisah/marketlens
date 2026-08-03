<?php

namespace App\Enums;

enum BusinessModule: string
{
    case Services = 'services';
    case Shareholders = 'shareholders';

    public function displayName(): string
    {
        return match ($this) {
            self::Services => 'Services and Revenue Sharing',
            self::Shareholders => 'Shareholders and Profit Distribution',
        };
    }
}
