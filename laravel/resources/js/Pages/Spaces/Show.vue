<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    analytics: {
        type: Object,
        default: () => ({ views: 0, clicks: 0, click_rate: 0, days: 30 }),
    },
    widgetsCount: {
        type: Number,
        default: 0,
    },
    can: {
        type: Object,
        default: () => ({ update: true, delete: true, moderate: true }),
    },
});

const { t, tc } = useTranslations();

const copied = ref(false);
const embedCopied = ref(false);

const copyEmbed = async () => {
    try {
        await navigator.clipboard.writeText(props.space.embed_snippet);
        embedCopied.value = true;
        setTimeout(() => (embedCopied.value = false), 2000);
    } catch (e) {
        window.prompt(t('spaces.copy_prompt'), props.space.embed_snippet);
    }
};

const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.space.collection_url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        window.prompt(t('spaces.copy_prompt'), props.space.collection_url);
    }
};
</script>

<template>
    <Head :title="space.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ space.name }}
                </h2>
                <div class="flex items-center gap-3">
                    <span
                        v-if="!can.update"
                        class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800"
                        :title="t('spaces.read_only_hint')"
                    >
                        {{ t('spaces.read_only') }}
                    </span>
                    <template v-if="can.update">
                        <Link
                            :href="route('spaces.branding.edit', space.slug)"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                        >
                            {{ t('spaces.branding') }}
                        </Link>
                        <Link
                            :href="route('spaces.edit', space.slug)"
                            class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                        >
                            {{ t('common.edit') }}
                        </Link>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-base font-semibold text-gray-900">
                            {{ t('spaces.collection_link') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ t('spaces.collection_help') }}
                        </p>
                        <div class="mt-3 flex items-center gap-3">
                            <code
                                class="flex-1 truncate rounded-md bg-gray-100 px-3 py-2 font-mono text-sm text-gray-800"
                            >
                                {{ space.collection_url }}
                            </code>
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                                @click="copy"
                            >
                                {{ copied ? t('common.copied') : t('common.copy') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-base font-semibold text-gray-900">
                                {{ t('spaces.details') }}
                            </h3>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">{{ t('spaces.slug') }}</dt>
                                    <dd class="font-mono text-gray-900">
                                        {{ space.slug }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">
                                        {{ t('spaces.custom_domain') }}
                                    </dt>
                                    <dd class="text-gray-900">
                                        {{ space.custom_domain || '—' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">{{ t('spaces.status') }}</dt>
                                    <dd>
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-medium"
                                            :class="
                                                space.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-600'
                                            "
                                        >
                                            {{
                                                space.is_active
                                                    ? t('spaces.active')
                                                    : t('spaces.inactive')
                                            }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">{{ t('spaces.created') }}</dt>
                                    <dd class="text-gray-900">
                                        {{
                                            new Date(
                                                space.created_at,
                                            ).toLocaleDateString()
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-base font-semibold text-gray-900">
                                {{ t('branding.preview_title') }}
                            </h3>
                            <div
                                class="mt-4 rounded-lg border border-gray-200 p-6"
                                :style="{
                                    backgroundColor: space.branding.background_color,
                                    fontFamily: space.branding.font_family,
                                }"
                            >
                                <div v-if="space.branding.logo_url" class="mb-4">
                                    <img
                                        :src="space.branding.logo_url"
                                        alt="Logo"
                                        class="h-10 object-contain"
                                    />
                                </div>
                                <p
                                    class="text-sm"
                                    :style="{ color: space.branding.primary_color }"
                                >
                                    {{ space.branding.thank_you_message }}
                                </p>
                                <button
                                    type="button"
                                    class="mt-4 rounded-md px-4 py-2 text-sm font-medium text-white"
                                    :style="{ backgroundColor: space.branding.primary_color }"
                                >
                                    {{ space.branding.button_text }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-base font-semibold text-gray-900">
                                {{ t('analytics.performance_title') }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ t('analytics.last_days', { days: analytics.days }) }}
                            </p>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">
                                        {{ t('analytics.widget_views') }}
                                    </dt>
                                    <dd class="font-medium text-gray-900">
                                        {{ analytics.views.toLocaleString() }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">
                                        {{ t('analytics.card_clicks') }}
                                    </dt>
                                    <dd class="font-medium text-gray-900">
                                        {{ analytics.clicks.toLocaleString() }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">
                                        {{ t('analytics.click_rate') }}
                                    </dt>
                                    <dd class="font-medium text-gray-900">
                                        {{ analytics.click_rate }}%
                                    </dd>
                                </div>
                            </dl>
                            <Link
                                :href="route('spaces.analytics', space.slug)"
                                class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                {{ t('analytics.open') }} →
                            </Link>
                        </div>
                    </div>

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
                            <button
                                type="button"
                                class="mt-4 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                                @click="copyEmbed"
                            >
                                {{
                                    embedCopied
                                        ? t('common.copied')
                                        : t('widgets.copy_snippet')
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="flex flex-wrap items-center justify-between gap-4 p-6">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ t('testimonials.title') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{
                                        t('spaces.collected_and_pending', {
                                            collected: space.testimonials_count,
                                            pending: space.pending_count,
                                        })
                                    }}
                                </p>
                            </div>
                            <Link
                                :href="route('spaces.testimonials.index', space.slug)"
                                class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                            >
                                {{ t('spaces.open_inbox') }}
                            </Link>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="flex flex-wrap items-center justify-between gap-4 p-6">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ t('widgets.title') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{
                                        tc(
                                            'widgets.embeds_configured',
                                            widgetsCount,
                                        )
                                    }}
                                </p>
                            </div>
                            <Link
                                :href="route('spaces.widgets.index', space.slug)"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                            >
                                {{ t('spaces.manage_widgets') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
