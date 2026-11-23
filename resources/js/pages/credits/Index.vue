<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import MoneyAmount from '@/components/MoneyAmount.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { index, store } from '@/routes/credits';
import type { CreditPack, CreditTransaction, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Credits', href: index() }],
    },
});

defineProps<{
    packs: CreditPack[];
    transactions: Paginated<CreditTransaction>;
}>();

const page = usePage();
const credits = computed(() => page.props.credits);

const purchase = useForm({ amount: 0 });

function buy(pack: CreditPack): void {
    purchase.amount = pack.amount;
    purchase.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Credits" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Credits"
            description="Credits are US dollars. Each image is charged its exact cost once it's ready, and failed generations are free."
        />

        <dl v-if="credits" class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Balance</dt>
                <dd class="mt-1 text-2xl font-semibold">
                    <MoneyAmount :value="credits.balance" />
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">
                    Held for images in progress
                </dt>
                <dd class="mt-1 text-2xl font-semibold">
                    <MoneyAmount :value="credits.reserved" />
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Available</dt>
                <dd class="mt-1 text-2xl font-semibold">
                    <MoneyAmount :value="credits.available" />
                </dd>
            </div>
        </dl>

        <section class="grid gap-3">
            <h2 class="text-base font-medium">Buy credits</h2>
            <div class="flex flex-wrap gap-3">
                <Button
                    v-for="pack in packs"
                    :key="pack.amount"
                    variant="outline"
                    size="lg"
                    :disabled="purchase.processing"
                    @click="buy(pack)"
                >
                    <Spinner
                        v-if="
                            purchase.processing &&
                            purchase.amount === pack.amount
                        "
                    />
                    Add {{ pack.label }}
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                This is a demo: purchases are simulated and no payment is taken.
            </p>
        </section>

        <section class="grid gap-3">
            <h2 class="text-base font-medium">History</h2>
            <div
                v-if="transactions.data.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No purchases or charges yet.
            </div>
            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium">Description</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transaction in transactions.data"
                            :key="transaction.id"
                            class="border-t"
                        >
                            <td
                                class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(transaction.created_at) }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-medium">{{
                                    transaction.label
                                }}</span>
                                <span
                                    v-if="transaction.description"
                                    class="block text-xs text-muted-foreground"
                                    >{{ transaction.description }}</span
                                >
                            </td>
                            <td
                                class="px-4 py-2 text-right font-medium"
                                :class="{
                                    'text-emerald-700 dark:text-emerald-400':
                                        transaction.type === 'purchase',
                                }"
                            >
                                <template v-if="transaction.type === 'purchase'"
                                    >+</template
                                ><MoneyAmount :value="transaction.amount" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <SimplePagination :page="transactions" />
        </section>
    </div>
</template>
