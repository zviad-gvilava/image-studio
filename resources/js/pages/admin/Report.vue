<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronRight, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import ReconciliationBadge from '@/components/admin/ReconciliationBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MoneyAmount from '@/components/MoneyAmount.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { countOf } from '@/lib/format';
import { report } from '@/routes/admin';
import type { ProfitReport } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profit report', href: report() }],
    },
});

const props = defineProps<ProfitReport>();

const range = useForm({ from: props.range.from, to: props.range.to });
const expandedDays = ref(new Set<string>());

function apply(): void {
    range.get(report.url(), { preserveScroll: true });
}

function toggle(date: string): void {
    const next = new Set(expandedDays.value);

    if (!next.delete(date)) {
        next.add(date);
    }

    expandedDays.value = next;
}
</script>

<template>
    <Head title="Profit report" />

    <div class="flex w-full flex-col gap-8 p-4 md:p-6">
        <div>
            <Heading
                title="Profit report"
                description="What users were charged, what Pixelforge charged us, and the difference. Days are the UTC days on which generations completed, which is how Pixelforge bills."
            />
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="apply"
            >
                <div class="grid gap-1">
                    <Label for="from" class="text-xs">From</Label>
                    <Input
                        id="from"
                        v-model="range.from"
                        type="date"
                        class="w-40"
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="to" class="text-xs">To</Label>
                    <Input
                        id="to"
                        v-model="range.to"
                        type="date"
                        class="w-40"
                    />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="range.processing"
                    >Apply</Button
                >
                <span class="pb-2 text-xs text-muted-foreground"
                    >Up to {{ max_days }} days</span
                >
            </form>
            <InputError
                class="mt-2"
                :message="range.errors.from ?? range.errors.to"
            />
        </div>

        <Alert v-if="!pixelforge_available">
            <TriangleAlert />
            <AlertTitle>Pixelforge's usage report is unavailable</AlertTitle>
            <AlertDescription>
                Our own numbers are shown, but they can't be checked against
                Pixelforge right now. Reload to try again.
            </AlertDescription>
        </Alert>

        <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Charged to users</dt>
                <dd class="mt-1 text-xl font-semibold">
                    <MoneyAmount :value="totals.charged" exact />
                </dd>
                <dd class="text-xs text-muted-foreground">
                    {{ countOf(totals.generations, 'generation') }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Pixelforge cost</dt>
                <dd class="mt-1 text-xl font-semibold">
                    <MoneyAmount :value="totals.provider_cost" exact />
                </dd>
                <dd class="text-xs text-muted-foreground">From our records</dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Profit</dt>
                <dd class="mt-1 text-xl font-semibold">
                    <MoneyAmount :value="totals.profit" exact />
                </dd>
                <dd class="text-xs text-muted-foreground">
                    {{
                        totals.margin_percent === null
                            ? 'No sales yet'
                            : `${totals.margin_percent}% margin`
                    }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">
                    Against Pixelforge's usage report
                </dt>
                <dd class="mt-1 text-sm font-medium">
                    <ReconciliationBadge :comparison="totals" />
                </dd>
                <dd
                    v-if="totals.pixelforge"
                    class="text-xs text-muted-foreground"
                >
                    They report
                    <MoneyAmount :value="totals.pixelforge.cost" exact /> for
                    {{ countOf(totals.pixelforge.generations, 'generation') }}
                </dd>
            </div>
        </dl>

        <section class="grid gap-3">
            <h2 class="text-base font-medium">By day</h2>
            <div
                v-if="days.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No completed generations in this range.
            </div>
            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Day (UTC)</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Generations
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Pixelforge cost
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Profit
                            </th>
                            <th class="px-4 py-2 font-medium">
                                Pixelforge's report
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <template v-for="day in days" :key="day.date">
                            <tr class="border-t">
                                <td class="px-4 py-2 font-sans text-sm">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-medium"
                                        :aria-expanded="
                                            expandedDays.has(day.date)
                                        "
                                        @click="toggle(day.date)"
                                    >
                                        <ChevronRight
                                            class="size-4 transition-transform"
                                            :class="{
                                                'rotate-90': expandedDays.has(
                                                    day.date,
                                                ),
                                            }"
                                        />
                                        {{ day.date }}
                                    </button>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ day.generations }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <MoneyAmount :value="day.charged" exact />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <MoneyAmount
                                        :value="day.provider_cost"
                                        exact
                                    />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <MoneyAmount :value="day.profit" exact />
                                </td>
                                <td class="px-4 py-2 font-sans text-sm">
                                    <ReconciliationBadge :comparison="day" />
                                </td>
                            </tr>
                            <template v-if="expandedDays.has(day.date)">
                                <tr
                                    v-for="line in day.users"
                                    :key="`${day.date}-${line.user.id}`"
                                    class="bg-muted/20 text-muted-foreground"
                                >
                                    <td class="py-1.5 pr-4 pl-10 font-sans">
                                        {{ line.user.name }}
                                        <span
                                            v-if="line.user.email"
                                            class="block text-[11px]"
                                            >{{ line.user.email }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-1.5 text-right">
                                        {{ line.generations }}
                                    </td>
                                    <td class="px-4 py-1.5 text-right">
                                        <MoneyAmount
                                            :value="line.charged"
                                            exact
                                        />
                                    </td>
                                    <td class="px-4 py-1.5 text-right">
                                        <MoneyAmount
                                            :value="line.provider_cost"
                                            exact
                                        />
                                    </td>
                                    <td class="px-4 py-1.5 text-right">
                                        <MoneyAmount
                                            :value="line.profit"
                                            exact
                                        />
                                    </td>
                                    <td />
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-3">
            <h2 class="text-base font-medium">By user</h2>
            <div
                v-if="users.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No users were charged in this range.
            </div>
            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">User</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Generations
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Pixelforge cost
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <tr
                            v-for="line in users"
                            :key="line.user.id ?? 'deleted'"
                            class="border-t"
                        >
                            <td class="px-4 py-2 font-sans text-sm">
                                {{ line.user.name }}
                                <span
                                    v-if="line.user.email"
                                    class="block text-xs text-muted-foreground"
                                    >{{ line.user.email }}</span
                                >
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ line.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <MoneyAmount :value="line.charged" exact />
                            </td>
                            <td class="px-4 py-2 text-right">
                                <MoneyAmount
                                    :value="line.provider_cost"
                                    exact
                                />
                            </td>
                            <td class="px-4 py-2 text-right">
                                <MoneyAmount :value="line.profit" exact />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
