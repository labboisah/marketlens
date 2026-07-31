<?php

namespace App\Domain\Legal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocumentVersion extends Model
{
    protected $fillable = [
        'legal_document_id',
        'version',
        'summary',
        'body',
        'published_at',
        'effective_at',
        'is_published',
        'is_material',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'effective_at' => 'datetime',
            'is_published' => 'boolean',
            'is_material' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }
}
