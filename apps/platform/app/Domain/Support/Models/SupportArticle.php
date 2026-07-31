<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportArticle extends Model
{
    protected $fillable = ['support_knowledge_category_id', 'title', 'slug', 'summary', 'body', 'is_faq', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['is_faq' => 'boolean', 'is_published' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportKnowledgeCategory::class, 'support_knowledge_category_id');
    }
}
