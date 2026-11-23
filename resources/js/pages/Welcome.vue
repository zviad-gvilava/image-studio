<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, Receipt, Sparkles } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { login, register, studio } from '@/routes';

const features = [
    {
        icon: Receipt,
        title: 'Know the price first',
        text: 'See what an image can cost before you create it, then pay its exact cost when it is ready.',
    },
    {
        icon: Activity,
        title: 'Watch it happen',
        text: 'Every image shows whether it is queued, generating, done or failed, live.',
    },
    {
        icon: Sparkles,
        title: 'Never pay for failures',
        text: 'If a generation fails, it is free, and you can see why it failed.',
    },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between p-6"
        >
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-7" />
                {{ $page.props.name }}
            </div>
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="studio()">Open studio</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Create an account</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-16 px-6 py-16"
        >
            <div class="max-w-2xl">
                <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                    Turn a sentence into an image.
                </h1>
                <p class="mt-4 text-lg text-muted-foreground">
                    Describe what you want, pick a size and quality, and see
                    what it costs before you start. Pay as you go with credits.
                </p>
                <div class="mt-8">
                    <Button size="lg" as-child>
                        <Link
                            :href="
                                $page.props.auth.user ? studio() : register()
                            "
                        >
                            <Sparkles />
                            Start creating
                        </Link>
                    </Button>
                </div>
            </div>

            <ul class="grid gap-6 sm:grid-cols-3">
                <li
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border p-5"
                >
                    <component
                        :is="feature.icon"
                        class="size-5 text-muted-foreground"
                    />
                    <h2 class="mt-3 font-medium">{{ feature.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ feature.text }}
                    </p>
                </li>
            </ul>
        </main>
    </div>
</template>
