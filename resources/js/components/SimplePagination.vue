<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    page: Paginated<unknown>;
    previousLabel?: string;
    nextLabel?: string;
}>();
</script>

<template>
    <nav
        v-if="page.meta.last_page > 1"
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        aria-label="Pagination"
    >
        <Button
            variant="outline"
            size="sm"
            :disabled="!page.links.prev"
            as-child
        >
            <Link
                v-if="page.links.prev"
                :href="page.links.prev"
                preserve-scroll
            >
                <ChevronLeft /> {{ previousLabel ?? 'Previous' }}
            </Link>
            <span v-else
                ><ChevronLeft /> {{ previousLabel ?? 'Previous' }}</span
            >
        </Button>
        <span
            >Page {{ page.meta.current_page }} of
            {{ page.meta.last_page }}</span
        >
        <Button
            variant="outline"
            size="sm"
            :disabled="!page.links.next"
            as-child
        >
            <Link
                v-if="page.links.next"
                :href="page.links.next"
                preserve-scroll
            >
                {{ nextLabel ?? 'Next' }} <ChevronRight />
            </Link>
            <span v-else>{{ nextLabel ?? 'Next' }} <ChevronRight /></span>
        </Button>
    </nav>
</template>
