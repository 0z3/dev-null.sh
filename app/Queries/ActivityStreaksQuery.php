<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final readonly class ActivityStreaksQuery
{
    /**
     * The longest and current runs of consecutive days with at least one row. Days are grouped like the activity
     * graph, by the date of `created_at` in the app timezone. The current streak still counts when the last row was
     * yesterday, since today is not over yet.
     *
     * @return array{longest: int, current: int}
     */
    public function get(Builder $rows): array
    {
        /** @var list<string> $days */
        $days = $rows
            ->selectRaw('date(created_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->all();

        $longest = 0;
        $streak = 0;
        $previous = null;

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day);
            $streak = $previous?->addDay()->isSameDay($date) ? $streak + 1 : 1;
            $longest = max($longest, $streak);
            $previous = $date;
        }

        $isAlive = $previous?->greaterThanOrEqualTo(CarbonImmutable::yesterday()) ?? false;

        return [
            'longest' => $longest,
            'current' => $isAlive ? $streak : 0,
        ];
    }
}
