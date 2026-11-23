<script setup lang="ts">
import { X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineProps<{
    url: string;
    label: string;
    error?: string;
}>();

const emit = defineEmits<{
    update: [url: string];
    remove: [];
}>();
</script>

<template>
    <div class="grid gap-1">
        <div class="flex gap-2">
            <Input
                :model-value="url"
                type="url"
                inputmode="url"
                placeholder="https://example.com/style.png"
                :aria-label="label"
                :aria-invalid="error ? true : undefined"
                @update:model-value="emit('update', String($event))"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :aria-label="`Remove ${label.toLowerCase()}`"
                @click="emit('remove')"
            >
                <X />
            </Button>
        </div>
        <InputError :message="error" />
    </div>
</template>
