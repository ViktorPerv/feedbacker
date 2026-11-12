<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({ views: 0, clicks: 0, click_rate: 0, days: 30 }),
    },
    series: {
        type: Array,
        default: () => [],
    },
    topTestimonials: {
        type: Array,
        default: () => [],
    },
    window: {
        type: Number,
        default: 30,
    },
    windows: {
        type: Array,
        default: () => [7, 30, 90],
    },
});

const { t, tc } = useTranslations();

const copied = ref(false);

// The tallest bar defines the scale; guard against an all-zero series.
const peak = computed(() => Math.max(1, ...props.series.map((point) => point.views)));

const barHeight = (value) => {
    if (value === 0) return '2px';
    return `${Math.max(4, Math.round((value / peak.value) * 100))}%`;
};

const formatDay = (value) =>
    new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });

const setWindow = (days) => {
    router.get(
        route('spaces.analytics', props.space.slug),
        { days },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const copyEmbed = async () => {
    // The snippet is assembled on the server: writing a literal closing script
    // tag here would terminate this SFC's script block.
    const snippet = props.space.embed_snippet;

    try {
        await navigator.clipboard.writeText(snippet);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        window.prompt(t('spaces.copy_prompt'), snippet);
    }
};
</script>

<template>
    <Head :title="`${t('analytics.title')} — ${space.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ t('analytics.title') }} — {{ space.name }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('analytics.last_days', { days: window }) }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('spaces.show', space.slug)"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        {{ t('spaces.back_to_space') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Window selector -->
                <div class="flex gap-2">
                    <button
                        v-for="option in windows"
                        :key="option"
                        type="button"
                        class="rounded-full px-3 py-1 text-sm font-medium transition"
                        :class="
                            window === option
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        "
                        @click="setWindow(option)"
                    >
                        {{ t('analytics.days', { count: option }) }}
                    </button>
                </div>

                <!-- Headline numbers -->
                <div class="grid gap-6 sm:grid-cols-3">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">
                                {{ t('analytics.widget_views') }}
                            </h3>
                            <p class="mt-2 text-3xl font-semibold text-gray-900">
                                {{ summary.views.toLocaleString() }}
                            </p>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">
                                {{ t('analytics.card_clicks') }}
                            </h3>
                            <p class="mt-2 text-3xl font-semibold text-gray-900">
                                {{ summary.clicks.toLocaleString() }}
                            </p>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">
                                {{ t('analytics.click_rate') }}
                            </h3>
                            <p class="mt-2 text-3xl font-semibold text-gray-900">
                                {{ summary.click_rate }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Daily chart -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-base font-semibold text-gray-900">
                            {{ t('analytics.views_per_day') }}
                        </h3>

                        <div
                            v-if="summary.views === 0"
                            class="mt-6 rounded-md bg-gray-50 px-4 py-8 text-center text-sm text-gray-500"
                        >
                            {{ t('analytics.no_impressions') }}
                        </div>

                        <div v-else class="mt-6">
                            <div
                                class="flex h-40 items-end gap-1"
                                role="img"
                                :aria-label="t('analytics.views_per_day')"
                            >
                                <div
                                    v-for="point in series"
                                    :key="point.date"
                                    class="group relative flex-1"
                                    :style="{ height: barHeight(point.views) }"
                                    :title="`${formatDay(point.date)}: ${point.views} / ${point.clicks}`"
                                >
                                    <div
                                        class="h-full w-full rounded-t bg-indigo-500 transition group-hover:bg-indigo-600"
                                    ></div>
                                </div>
                            </div>

                            <div class="mt-2 flex justify-between text-xs text-gray-400">
                                <span>{{ formatDay(series[0]?.date) }}</span>
                                <span>{{ formatDay(series[series.length - 1]?.date) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Top testimonials -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-base font-semibold text-gray-900">
                                {{ t('analytics.most_clicked') }}
                            </h3>

                            <p
                                v-if="topTestimonials.length === 0"
                                class="mt-4 text-sm text-gray-500"
                            >
                                {{ t('analytics.no_clicks') }}
                            </p>

                            <ul v-else class="mt-4 space-y-4">
                                <li
                                    v-for="item in topTestimonials"
                                    :key="item.id"
                                    class="border-b border-gray-100 pb-4 last:border-0 last:pb-0"
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ item.author_name }}
                                            </p>
                                            <p class="mt-1 truncate text-sm text-gray-500">
                                                {{ item.content }}
                                            </p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700"
                                        >
                                            {{ tc('analytics.clicks', item.clicks) }}
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Embed snippet -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-base font-semibold text-gray-900">
                                {{ t('analytics.embed_title') }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ t('analytics.embed_help') }}
                            </p>

                            <pre
                                class="mt-4 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs leading-relaxed text-gray-100"
                            ><code>{{ space.embed_snippet }}</code></pre>

                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                                    @click="copyEmbed"
                                >
                                    {{
                                        copied
                                            ? t('common.copied')
                                            : t('widgets.copy_snippet')
                                    }}
                                </button>
                                <a
                                    :href="space.embed_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    {{ t('widgets.open_script') }} ↗
                                </a>
                            </div>

                            <p class="mt-4 text-xs text-gray-400">
                                {{ t('analytics.query_hint') }}
                                <code class="rounded bg-gray-100 px-1"
                                    >?theme=dark&amp;columns=2&amp;limit=12</code
                                >
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
