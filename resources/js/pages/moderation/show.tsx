import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import CommitApprovalController from '@/actions/App/Http/Controllers/CommitApprovalController';
import CommitRejectionController from '@/actions/App/Http/Controllers/CommitRejectionController';
import { store as approvePendingCommits } from '@/actions/App/Http/Controllers/PendingCommitApprovalController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { Commit, CursorPaginated } from '@/types';

function ModerationActions({
    commit,
    disabled,
}: {
    commit: Commit;
    disabled: boolean;
}) {
    const [processing, setProcessing] = useState(false);

    const moderate = (
        route:
            | typeof CommitApprovalController.store
            | typeof CommitRejectionController.store,
    ) =>
        router.visit(route(commit), {
            preserveScroll: true,
            reset: ['commits'],
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });

    return (
        <div className="mt-3 flex gap-2">
            <Button
                size="sm"
                disabled={processing || disabled}
                onClick={() => moderate(CommitApprovalController.store)}
                data-test="approve-commit-button"
            >
                <Check />
                Approve
            </Button>
            <Button
                variant="outline"
                size="sm"
                disabled={processing || disabled}
                onClick={() => moderate(CommitRejectionController.store)}
                data-test="reject-commit-button"
            >
                <X />
                Reject
            </Button>
        </div>
    );
}

export default function Show({
    commits,
}: {
    commits: CursorPaginated<Commit>;
}) {
    const [approvingAll, setApprovingAll] = useState(false);

    const approveAll = () =>
        router.visit(approvePendingCommits(), {
            data: { commit_ids: commits.data.map((commit) => commit.id) },
            reset: ['commits'],
            onStart: () => setApprovingAll(true),
            onFinish: () => setApprovingAll(false),
        });

    return (
        <>
            <Head title="Moderation" />

            <div className="sticky top-0 z-10 flex h-14 items-center border-b bg-card px-4">
                <h1 className="font-semibold">Moderation</h1>
                <p className="ml-auto text-sm text-muted-foreground">
                    Oldest first
                </p>
            </div>

            {commits.data.length > 0 && (
                <div className="flex justify-end border-b px-4 py-3">
                    <Button
                        size="sm"
                        disabled={approvingAll}
                        onClick={approveAll}
                        data-test="approve-all-pending-commits-button"
                    >
                        <Check />
                        {approvingAll
                            ? 'Approving…'
                            : `Approve ${commits.data.length} pending ${commits.data.length === 1 ? 'commit' : 'commits'}`}
                    </Button>
                </div>
            )}

            <CommitFeed
                commits={commits}
                firstLane="var(--rail)"
                end="That's every pending commit."
                actions={(commit) => (
                    <ModerationActions
                        commit={commit}
                        disabled={approvingAll}
                    />
                )}
                empty={
                    <EmptyFeed command="nothing to commit, working tree clean">
                        No commits are waiting for review.
                    </EmptyFeed>
                }
            />
        </>
    );
}

Show.layout = AppLayout;
