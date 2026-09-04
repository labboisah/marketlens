<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportKnowledgeCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(SupportArticle::class, 'support_knowledge_category_id');
    }
}
