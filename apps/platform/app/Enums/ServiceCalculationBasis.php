<?php

namespace App\Enums;

enum ServiceCalculationBasis: string
{
    case GrossAmount = 'gross_amount';
    case NetAfterDiscount = 'net_after_discount';
    case NetAfterDirectExpenses = 'net_after_direct_expenses';
    case NetAfterDiscountAndDirectExpenses = 'net_after_discount_and_direct_expenses';
    case FixedAmount = 'fixed_amount';
}
