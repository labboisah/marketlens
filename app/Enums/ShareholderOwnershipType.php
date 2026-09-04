<?php

namespace App\Enums;

enum ShareholderOwnershipType: string
{
    case SoleProprietorship = 'sole_proprietorship';
    case Partnership = 'partnership';
    case LimitedCompany = 'limited_company';
    case Cooperative = 'cooperative';
    case Other = 'other';
}
