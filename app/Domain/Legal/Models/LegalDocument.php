<?php

namespace App\Domain\Legal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegalDocument extends Model
{
    protected $fillable = ['slug', 'title', 'description', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class);
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(LegalDocumentVersion::class)
            ->where('is_published', true)
            ->latest('effective_at')
            ->latest('published_at');
    }
}
