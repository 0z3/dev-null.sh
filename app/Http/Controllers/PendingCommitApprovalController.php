<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApprovePendingCommits;
use App\Http\Requests\ApprovePendingCommitsRequest;
use Illuminate\Http\RedirectResponse;

final readonly class PendingCommitApprovalController
{
    public function store(ApprovePendingCommitsRequest $request, ApprovePendingCommits $action): RedirectResponse
    {
        /** @var list<string> $commitIds */
        $commitIds = $request->validated('commit_ids');

        $action->handle($commitIds);

        return to_route('moderation.show');
    }
}
