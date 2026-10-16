import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function PageTabs({
    label,
    tabs,
    preserveScroll = false,
    prefetch = true,
}: {
    label: string;
    preserveScroll?: boolean;
    prefetch?: boolean;
    tabs: {
        title: string;
        href: NonNullable<InertiaLinkProps['href']>;
        isActive: boolean;
        disabledReason?: string;
    }[];
}) {
    const indicatorName = `tab-indicator-${label.toLowerCase().replaceAll(/[^a-z0-9]+/gu, '-')}`;

    return (
        <nav aria-label={label} className="flex border-b">
            {tabs.map((tab) =>
                tab.disabledReason ? (
                    <span
                        key={tab.title}
                        aria-disabled
                        title={tab.disabledReason}
                        className="flex flex-1 cursor-not-allowed justify-center text-[15px] font-medium text-muted-foreground/50"
                    >
                        <span className="py-3.5">{tab.title}</span>
                    </span>
                ) : (
                    <Link
                        key={tab.title}
                        href={tab.href}
                        prefetch={prefetch}
                        viewTransition
                        preserveScroll={preserveScroll}
                        aria-current={tab.isActive ? 'page' : undefined}
                        className={cn(
                            'flex flex-1 justify-center text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                            { 'text-foreground': tab.isActive },
                        )}
                    >
                        <span className="relative py-3.5">
                            {tab.title}
                            {tab.isActive && (
                                <span
                                    aria-hidden
                                    className="absolute inset-x-0 bottom-0 h-[3px] bg-primary [view-transition-class:tab-indicator]"
                                    style={{
                                        viewTransitionName: indicatorName,
                                    }}
                                />
                            )}
                        </span>
                    </Link>
                ),
            )}
        </nav>
    );
}
