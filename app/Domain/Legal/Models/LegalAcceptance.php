<?php

namespace App\Domain\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalAcceptance extends Model
{
    protected $fillable = ['user_id', 'legal_document_version_id', 'accepted_at', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }
}
