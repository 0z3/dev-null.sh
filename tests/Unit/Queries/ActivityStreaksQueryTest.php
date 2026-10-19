<?php

declare(strict_types=1);

use App\Enums\ActivityMetric;
use App\Models\Commit;
use App\Models\User;
use App\Queries\ActivityStreaksQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
});

it('counts the consecutive days with a signup', function (): void {
    User::factory()->create();
    User::factory()->create(['created_at' => now()->subDay()]);
    User::factory()->create(['created_at' => now()->subDays(3)]);

    expect(resolve(ActivityStreaksQuery::class)->get(ActivityMetric::Signups->rows()))
        ->toBe(['longest' => 2, 'current' => 2]);
});

it('counts the replies apart from the top-level commits', function (): void {
    $commit = Commit::factory()->create(['created_at' => now()->subDays(5)]);
    Commit::factory()->for($commit, 'parent')->create(['created_at' => now()->subDays(3)]);
    Commit::factory()->for($commit, 'parent')->create(['created_at' => now()->subDays(2)]);

    $query = resolve(ActivityStreaksQuery::class);

    expect($query->get(ActivityMetric::Replies->rows()))->toBe(['longest' => 2, 'current' => 0])
        ->and($query->get(ActivityMetric::Commits->rows()))->toBe(['longest' => 1, 'current' => 0]);
});
