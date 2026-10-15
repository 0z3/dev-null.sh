<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;

final readonly class ApprovePendingCommits
{
    /**
     * @param  list<string>  $commitIds
     */
    public function handle(array $commitIds): void
    {
        Commit::query()->pending()->whereIn('id', $commitIds)->update(['status' => CommitStatus::Approved]);
    }
}
