<script setup>
import { useTranslations } from '@/composables/useTranslations';
import { computed } from 'vue';

/**
 * A faithful-enough preview of what the Go bundle will render.
 *
 * The real widget is a separate, dependency-free script that runs inside a
 * shadow root. Re-implementing the four layouts here is the price of a live
 * preview in the builder; keep the two in sync when a layout changes.
 */
const props = defineProps({
    layout: {
        type: String,
        default: 'masonry',
    },
    config: {
        type: Object,
        required: true,
    },
});

const { t, tc } = useTranslations();

const SAMPLES = [
    {
        name: 'Alice Turner',
        title: 'CTO',
        company: 'Globex',
        rating: 5,
        content:
            'Cut our onboarding time in half. The team actually uses it every day.',
        featured: true,
        video: false,
    },
    {
        name: 'Ben Okafor',
        title: 'Founder',
        company: 'Northwind',
        rating: 4,
        content: 'Straightforward to set up and the embeds look good.',
        featured: false,
        video: true,
    },
    {
        name: 'Chloe Martin',
        title: 'Head of Support',
        company: 'Initech',
        rating: 5,
        content: 'Our customers finally have somewhere to leave praise.',
        featured: false,
        video: false,
    },
];

const isDark = computed(() => props.config.theme === 'dark');

const accent = computed(() => props.config.accent || '#4F46E5');

const surface = computed(() => (isDark.value ? '#111827' : '#ffffff'));
const border = computed(() => (isDark.value ? '#1f2937' : '#eeeeee'));
const heading = computed(() => (isDark.value ? '#f9fafb' : '#111827'));
const body = computed(() => (isDark.value ? '#d1d5db' : '#374151'));
const muted = computed(() => (isDark.value ? '#9ca3af' : '#6b7280'));

const visible = computed(() => {
    let items = SAMPLES;

    if (props.config.featured_only) {
        items = items.filter((item) => item.featured);
    }

    if (props.config.min_rating) {
        items = items.filter((item) => item.rating >= props.config.min_rating);
    }

    return items.slice(0, props.layout === 'single' ? 1 : props.config.limit || 12);
});

const average = computed(() => {
    if (visible.value.length === 0) return 0;
    const total = visible.value.reduce((sum, item) => sum + item.rating, 0);
    return (total / visible.value.length).toFixed(1);
});

const stars = (rating) => '★'.repeat(rating) + '☆'.repeat(5 - rating);

const initials = (name) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

const wallStyle = computed(() => ({
    backgroundColor: isDark.value ? '#0b1220' : '#f9fafb',
    padding: '20px',
    borderRadius: '12px',
    border: `1px solid ${border.value}`,
}));

const cardStyle = computed(() => ({
    background: surface.value,
    border: `1px solid ${border.value}`,
}));

const gridStyle = computed(() => ({
    columns: String(props.config.columns || 3),
    columnGap: '16px',
}));
</script>

<template>
    <div :style="wallStyle">
        <div class="preview-root" :class="{ 'is-carousel': layout === 'carousel' }">
            <!-- Heading -->
            <div
                v-if="config.heading"
                class="mb-4 text-center"
                :style="{ color: heading }"
            >
                <p class="text-base font-semibold">{{ config.heading }}</p>
                <p
                    v-if="config.ratings && layout !== 'badge'"
                    class="mt-1 text-xs"
                    :style="{ color: muted }"
                >
                    ★ {{ average }} average from {{ visible.length }}
                    {{ tc('testimonials.review_count', visible.length) }}
                </p>
            </div>

            <!-- Badge -->
            <div
                v-if="layout === 'badge'"
                class="flex flex-wrap items-center justify-center gap-3"
            >
                <div class="flex -space-x-2">
                    <div
                        v-for="item in visible"
                        :key="item.name"
                        class="flex h-9 w-9 items-center justify-center rounded-full border-2 text-xs font-bold text-white"
                        :style="{
                            background: accent,
                            borderColor: surface,
                        }"
                    >
                        {{ initials(item.name) }}
                    </div>
                </div>
                <div class="text-sm" :style="{ color: body }">
                    <span v-if="config.ratings" class="text-amber-500">★</span>
                    <strong :style="{ color: heading }">{{ average }}</strong>
                    <span :style="{ color: muted }">
                        · {{ tc('testimonials.review_count', visible.length) }}
                    </span>
                </div>
            </div>

            <!-- Empty -->
            <p
                v-else-if="visible.length === 0"
                class="py-8 text-center text-sm"
                :style="{ color: muted }"
            >
                No testimonials match these filters yet.
            </p>

            <!-- Single -->
            <div v-else-if="layout === 'single'" class="mx-auto max-w-lg">
                <article
                    class="rounded-xl p-5 shadow-sm"
                    :style="cardStyle"
                >
                    <span
                        v-if="visible[0].featured"
                        class="mb-2 inline-block rounded-full px-2 py-0.5 text-xs font-semibold text-white"
                        :style="{ background: accent }"
                    >
                        {{ t('testimonials.featured') }}
                    </span>
                    <p
                        v-if="config.ratings"
                        class="mb-2 text-sm text-amber-500"
                    >
                        {{ stars(visible[0].rating) }}
                    </p>
                    <p class="text-sm leading-relaxed" :style="{ color: body }">
                        {{ visible[0].content }}
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold text-white"
                            :style="{ background: accent }"
                        >
                            {{ initials(visible[0].name) }}
                        </div>
                        <div class="text-xs">
                            <strong :style="{ color: heading }">
                                {{ visible[0].name }}
                            </strong>
                            <span :style="{ color: muted }">
                                {{ visible[0].title }} ·
                                {{ visible[0].company }}
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Carousel -->
            <div
                v-else-if="layout === 'carousel'"
                class="flex gap-4 overflow-x-auto pb-2"
                style="scroll-snap-type: x mandatory"
            >
                <article
                    v-for="item in visible"
                    :key="item.name"
                    class="w-72 shrink-0 rounded-xl p-5 shadow-sm"
                    :style="{ ...cardStyle, scrollSnapAlign: 'start' }"
                >
                    <p
                        v-if="config.ratings"
                        class="mb-2 text-sm text-amber-500"
                    >
                        {{ stars(item.rating) }}
                    </p>
                    <p class="text-sm leading-relaxed" :style="{ color: body }">
                        {{ item.content }}
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold text-white"
                            :style="{ background: accent }"
                        >
                            {{ initials(item.name) }}
                        </div>
                        <div class="text-xs">
                            <strong :style="{ color: heading }">
                                {{ item.name }}
                            </strong>
                            <span :style="{ color: muted }">
                                {{ item.title }}
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Masonry -->
            <div v-else :style="gridStyle">
                <article
                    v-for="item in visible"
                    :key="item.name"
                    class="mb-4 rounded-xl p-5 shadow-sm"
                    :style="{ ...cardStyle, breakInside: 'avoid' }"
                >
                    <span
                        v-if="item.featured"
                        class="mb-2 inline-block rounded-full px-2 py-0.5 text-xs font-semibold text-white"
                        :style="{ background: accent }"
                    >
                        {{ t('testimonials.featured') }}
                    </span>
                    <p
                        v-if="config.ratings"
                        class="mb-2 text-sm text-amber-500"
                    >
                        {{ stars(item.rating) }}
                    </p>
                    <p class="text-sm leading-relaxed" :style="{ color: body }">
                        {{ item.content }}
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold text-white"
                            :style="{ background: accent }"
                        >
                            {{ initials(item.name) }}
                        </div>
                        <div class="text-xs">
                            <strong :style="{ color: heading }">
                                {{ item.name }}
                            </strong>
                            <span :style="{ color: muted }">
                                {{ item.title }} · {{ item.company }}
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Footer -->
            <p
                v-if="config.footer"
                class="mt-4 text-center text-xs"
                :style="{ color: muted }"
            >
                Powered by Feedbacker
            </p>
        </div>
    </div>
</template>
