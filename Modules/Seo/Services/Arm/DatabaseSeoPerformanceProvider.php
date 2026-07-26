<?php

namespace Modules\Seo\Services\Arm;

use Illuminate\Support\Collection;
use Modules\Seo\Contracts\SeoPerformanceProvider;
use Modules\Seo\Models\SeoProject;
use Modules\Seo\Models\SeoSnapshot;

/** فاز ۱: دادهٔ عملکرد از snapshot های دیتابیس (seed شده) خوانده می‌شود. */
class DatabaseSeoPerformanceProvider implements SeoPerformanceProvider
{
    public function latestSnapshot(SeoProject $project, ?string $until = null): ?SeoSnapshot
    {
        return $project->snapshots()
            ->when($until, fn ($q) => $q->whereDate('period_end', '<=', $until))
            ->orderByDesc('snapshot_date')
            ->first();
    }

    public function previousSnapshot(SeoProject $project, SeoSnapshot $current): ?SeoSnapshot
    {
        return $project->snapshots()
            ->where('snapshot_date', '<', $current->snapshot_date)
            ->orderByDesc('snapshot_date')
            ->first();
    }

    public function series(SeoProject $project, string $from, string $to): Collection
    {
        return $project->snapshots()
            ->whereDate('period_end', '>=', $from)
            ->whereDate('period_end', '<=', $to)
            ->orderBy('snapshot_date')
            ->get();
    }
}
