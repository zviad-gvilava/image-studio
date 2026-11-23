import { usePage } from '@inertiajs/vue3';
import { ChartColumn, Sparkles, Wallet } from '@lucide/vue';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { studio } from '@/routes';
import { report } from '@/routes/admin';
import { index as credits } from '@/routes/credits';
import type { NavItem } from '@/types';

/**
 * The app's main navigation, shared by the sidebar and header layouts.
 */
export function useNavigation(): ComputedRef<NavItem[]> {
    const page = usePage();

    return computed(() => [
        { title: 'Studio', href: studio(), icon: Sparkles },
        { title: 'Credits', href: credits(), icon: Wallet },
        ...(page.props.auth.user?.is_admin
            ? [{ title: 'Profit report', href: report(), icon: ChartColumn }]
            : []),
    ]);
}
