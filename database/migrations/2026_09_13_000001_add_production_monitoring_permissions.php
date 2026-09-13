<?php

use App\Domain\Access\Models\MonitoringPermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view-production' => 'View production operations', 'view-production-finance' => 'View production costs and shares', 'export-production' => 'Export production reports'] as $slug => $name) {
            MonitoringPermission::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }

    public function down(): void
    {
        MonitoringPermission::query()->whereIn('slug', ['view-production', 'view-production-finance', 'export-production'])->delete();
    }
};
