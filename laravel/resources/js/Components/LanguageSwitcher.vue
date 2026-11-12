<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // 'light' for light backgrounds, 'dark' for the dark top bar.
    variant: {
        type: String,
        default: 'light',
    },
});

const page = usePage();

const locales = computed(() => page.props.locales ?? []);
const current = computed(() => page.props.locale ?? 'en');

const classes = (code) => {
    if (current.value === code) {
        return props.variant === 'dark'
            ? 'bg-white/20 text-white'
            : 'bg-gray-900 text-white';
    }

    return props.variant === 'dark'
        ? 'text-gray-300 hover:bg-white/10 hover:text-white'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900';
};
</script>

<template>
    <div
        v-if="locales.length > 1"
        class="inline-flex items-center gap-1 rounded-md p-0.5"
        role="group"
        aria-label="Language"
    >
        <Link
            v-for="language in locales"
            :key="language.code"
            :href="route('locale.switch', language.code)"
            preserve-scroll
            :title="language.name"
            class="rounded px-2 py-1 text-xs font-semibold uppercase tracking-wide transition"
            :class="classes(language.code)"
        >
            {{ language.code }}
        </Link>
    </div>
</template>
