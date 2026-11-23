<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
import { computed } from 'vue';
import MoneyAmount from '@/components/MoneyAmount.vue';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as credits } from '@/routes/credits';

const page = usePage();
const summary = computed(() => page.props.credits);
</script>

<template>
    <SidebarMenu v-if="summary">
        <SidebarMenuItem>
            <SidebarMenuButton as-child tooltip="Credits">
                <Link :href="credits()">
                    <Wallet />
                    <span
                        class="flex flex-1 items-center justify-between gap-2"
                    >
                        <span class="text-muted-foreground">Available</span>
                        <MoneyAmount
                            :value="summary.available"
                            class="font-medium"
                        />
                    </span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
