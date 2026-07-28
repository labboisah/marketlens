<?php

namespace App\Enums;

enum ApplicationVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Deprecated = 'deprecated';
}
