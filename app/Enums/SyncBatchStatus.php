<?php

namespace App\Enums;

enum SyncBatchStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Processing = 'processing';
    case Accepted = 'accepted';
    case Partial = 'partial';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
