import { Head, router } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import {
    ActivityCells,
    activitySummary,
    activityWindows,
} from '@/components/activity-graph';
import type { ActivityNoun } from '@/components/activity-graph';
import { Breakdown } from '@/components/operations/breakdown';
import { PresetButton } from '@/components/operations/preset-button';
import { Section } from '@/components/operations/section';
import AppLayout from '@/layouts/app-layout';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/operations';
import type {
    ActivityBreakdowns,
    ActivityMetric,
    ActivityScale,
    SiteActivity,
    Volume,
} from '@/types';

const ACTIVITY_SCALES: { label: string; scale: ActivityScale }[] = [
    { label: '24h', scale: 'day' },
    { label: '7d', scale: 'week' },
    { label: '30d', scale: 'month' },
    { label: '1y', scale: 'year' },
];

const ACTIVITY_METRICS: ActivityMetric[] = ['commits', 'replies', 'signups'];

const NOUNS: Record<ActivityMetric, ActivityNoun> = {
    commits: ['commit', 'commits'],
    replies: ['reply', 'replies'],
    signups: ['signup', 'signups'],
};

function ActivityPicker({ activity }: { activity: SiteActivity }) {
    return (
        <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 font-mono text-[13px]">
            <div
                role="group"
                aria-label="Series"
                className="flex items-center gap-2"
            >
                {ACTIVITY_METRICS.map((metric) => (
                    <PresetButton
                        key={metric}
                        isActive={metric === activity.metric}
                        onClick={() =>
                            router.reload({
                                data: { metric },
                                only: ['activity'],
                            })
                        }
                    >
                        {metric}
                    </PresetButton>
                ))}
            </div>
            <div
                role="group"
                aria-label="Window"
                className="flex items-center gap-2 sm:ml-auto"
            >
                {ACTIVITY_SCALES.map((preset) => (
                    <PresetButton
                        key={preset.scale}
                        isActive={preset.scale === activity.scale}
                        onClick={() =>
                            router.reload({
                                data: { scale: preset.scale },
                                only: ['activity', 'breakdowns'],
                            })
                        }
                    >
                        {preset.label}
                    </PresetButton>
                ))}
            </div>
        </div>
    );
}

export default function Show({
    volume,
    activity,
    breakdowns,
}: {
    volume: Volume;
    activity: SiteActivity;
    breakdowns: ActivityBreakdowns;
}) {
    const posted = breakdowns.statuses.reduce(
        (sum, status) => sum + status.count,
        0,
    );

    const volumeRows = [
        { label: 'signups', counts: volume.signups },
        { label: 'commits', counts: volume.commits },
        { label: 'replies', counts: volume.replies },
    ];

    return (
        <>
            <Head title="Site activity" />

            <Section title="Volume">
                <div className="rounded-lg border px-4">
                    <table className="w-full table-fixed text-sm">
                        <thead>
                            <tr className="font-mono text-[13px] text-muted-foreground">
                                <th
                                    scope="col"
                                    className="py-2.5 text-left font-normal"
                                >
                                    <span className="sr-only">What</span>
                                </th>
                                <th
                                    scope="col"
                                    className="w-20 py-2.5 text-right font-normal sm:w-24"
                                >
                                    24h
                                </th>
                                <th
                                    scope="col"
                                    className="w-20 py-2.5 text-right font-normal sm:w-24"
                                >
                                    7d
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y border-t">
                            {volumeRows.map((row) => (
                                <tr key={row.label}>
                                    <th
                                        scope="row"
                                        className="py-2.5 text-left font-mono text-[13px] font-normal text-muted-foreground"
                                    >
                                        {row.label}
                                    </th>
                                    <td className="py-2.5 text-right font-mono text-[13px] font-medium tabular-nums">
                                        {formatCount(row.counts.day)}
                                    </td>
                                    <td className="py-2.5 text-right font-mono text-[13px] tabular-nums">
                                        {formatCount(row.counts.week)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Section>

            <Section
                title="Activity"
                meta={activitySummary(activity, NOUNS[activity.metric])}
            >
                <ActivityPicker activity={activity} />
                <ActivityCells
                    activity={activity}
                    color="var(--muted-foreground)"
                    noun={NOUNS[activity.metric]}
                />
            </Section>

            <Section
                title="Breakdown"
                meta={`${formatCount(posted)} ${posted === 1 ? 'commit or reply' : 'commits and replies'} ${activityWindows[activity.scale]}`}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Breakdown
                        title="status"
                        unit="commits"
                        empty="unknown"
                        rows={breakdowns.statuses}
                    />
                    <Breakdown
                        title="authors"
                        unit="commits"
                        empty="unknown"
                        rows={breakdowns.authors}
                        formatName={(name) => `@${name}`}
                        href={(name) => UserController.show.url(name)}
                    />
                </div>
            </Section>
        </>
    );
}

Show.layout = [AppLayout, OperationsLayout];
