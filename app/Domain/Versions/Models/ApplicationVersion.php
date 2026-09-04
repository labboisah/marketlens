<?php

namespace App\Domain\Versions\Models;

use App\Enums\ApplicationVersionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationVersion extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'application',
        'platform',
        'architecture',
        'version',
        'release_channel',
        'status',
        'release_notes',
        'minimum_supported_version',
        'recommended_version',
        'mandatory_update',
        'is_active',
        'download_disk',
        'download_path',
        'download_original_name',
        'download_mime_type',
        'download_size_bytes',
        'checksum',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationVersionStatus::class,
            'mandatory_update' => 'boolean',
            'is_active' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    public function hasDownload(): bool
    {
        return filled($this->download_disk) && filled($this->download_path);
    }
}
