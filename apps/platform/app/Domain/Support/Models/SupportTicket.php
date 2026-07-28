<?php

namespace App\Domain\Support\Models;

use App\Domain\Tenancy\Models\Business;
use App\Enums\SupportTicketStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = ['business_id', 'uuid', 'subject', 'status', 'priority'];

    protected function casts(): array
    {
        return ['status' => SupportTicketStatus::class];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
