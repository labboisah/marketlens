<?php

namespace App\Enums;

enum SettlementFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Manual = 'manual';
}
