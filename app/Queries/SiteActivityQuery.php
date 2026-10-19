<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ActivityScale;
use App\Models\Commit;
use Carbon\CarbonImmutable;

final readonly class SiteActivityQuery
{
    public function __construct(private ActivityBucketsQuery $buckets) {}

    /**
     * Top-level commits of everyone over the window the volume table counts, ending now, so the first cell only holds
     * the end of the hour or day it starts in, whatever their status since, deleted commits included, so the graph
     * agrees with the table.
     *
     * @return array{scale: ActivityScale, buckets: list<array{date: string, count: int}>}
     */
    public function get(ActivityScale $scale): array
    {
        $now = CarbonImmutable::now();

        return [
            'scale' => $scale,
            'buckets' => $this->buckets->get(Commit::withTrashed()->whereNull('parent_id')->toBase(), $scale, $scale->rollingStart($now), $now),
        ];
    }
}
