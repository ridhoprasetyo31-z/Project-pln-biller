import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import { NAVIGATION, type NavGroup, type NavItem } from '@/lib/navigation';
import { hasRoute } from '@/lib/utils';
import type { PageProps } from '@/types';

export interface ResolvedNavItem extends NavItem {
    href: string;
    path: string;
    active: boolean;
    badge: number;
}

export interface ResolvedNavGroup extends Omit<NavGroup, 'items'> {
    items: ResolvedNavItem[];
}

function pathOf(href: string): string {
    try {
        return new URL(href, window.location.origin).pathname;
    } catch {
        return href;
    }
}

/**
 * Menu sesuai peran. Item yang route-nya belum dibangun disembunyikan,
 * sehingga menu bertambah sendiri seiring tahap pengerjaan.
 */
export function useNavigation() {
    const page = usePage<PageProps>();
    const { auth, navBadges } = page.props;
    const currentPath = page.url.split('?')[0];

    return useMemo(() => {
        const groups: ResolvedNavGroup[] = NAVIGATION[auth.user.role]
            .map((group) => ({
                ...group,
                items: group.items
                    .filter((item) => hasRoute(item.route))
                    .map((item) => {
                        const href = route(item.route);
                        const path = item.match ?? pathOf(href);
                        return {
                            ...item,
                            href,
                            path,
                            active: currentPath === path || currentPath.startsWith(`${path}/`),
                            badge: navBadges?.[item.id] ?? 0,
                        };
                    }),
            }))
            .filter((group) => group.items.length > 0);

        let activeGroup: ResolvedNavGroup | undefined;
        let activeItem: ResolvedNavItem | undefined;
        for (const g of groups) {
            const found = g.items.find((i) => i.active);
            if (found) {
                activeGroup = g;
                activeItem = found;
                break;
            }
        }

        return { groups, activeGroup, activeItem };
    }, [auth.user.role, navBadges, currentPath]);
}
