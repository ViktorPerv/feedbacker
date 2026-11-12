<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    widgets: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({ create: true }),
    },
});

const { t } = useTranslations();

const copiedToken = ref(null);

const copySnippet = async (widget) => {
    try {
        await navigator.clipboard.writeText(widget.embed_snippet);
        copiedToken.value = widget.token;
        setTimeout(() => (copiedToken.value = null), 2000);
    } catch (e) {
        window.prompt(t('spaces.copy_prompt'), widget.embed_snippet);
    }
};

const destroy = (widget) => {
    if (confirm(t('widgets.delete_confirm', { name: widget.name }))) {
        router.delete(
            route('spaces.widgets.destroy', [props.space.slug, widget.token]),
        );
    }
};
</script>

<template>
    <Head :title="`${t('widgets.title')} — ${space.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ t('widgets.title') }} — {{ space.name }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('widgets.list_help') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('spaces.show', space.slug)"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        {{ t('spaces.back_to_space') }}
                    </Link>
                    <Link
                        v-if="can.create"
                        :href="route('spaces.widgets.create', space.slug)"
                    >
                        <PrimaryButton>{{ t('widgets.new') }}</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Empty -->
                <div
                    v-if="widgets.length === 0"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="p-12 text-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ t('widgets.empty') }}
                        </h3>
                        <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                            {{ t('widgets.empty_help') }}
                        </p>
                        <Link
                            v-if="can.create"
                            :href="route('spaces.widgets.create', space.slug)"
                            class="mt-6 inline-block"
                        >
                            <PrimaryButton>{{ t('widgets.create_first') }}</PrimaryButton>
                        </Link>
                    </div>
                </div>

                <!-- List -->
                <div v-else class="grid gap-6 lg:grid-cols-2">
                    <div
                        v-for="widget in widgets"
                        :key="widget.id"
                        class="flex flex-col overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="flex-1 p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-semibold text-gray-900">
                                        {{ widget.name }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ widget.layout_label }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2 py-1 text-xs font-medium"
                                    :class="
                                        widget.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{
                                        widget.is_active
                                            ? t('widgets.active')
                                            : t('widgets.inactive')
                                    }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2 text-xs text-gray-500">
                                <span class="rounded bg-gray-100 px-2 py-1">
                                    {{
                                        widget.config.theme === 'dark'
                                            ? t('widgets.dark')
                                            : t('widgets.light')
                                    }}
                                </span>
                                <span
                                    v-if="widget.layout === 'masonry'"
                                    class="rounded bg-gray-100 px-2 py-1"
                                >
                                    {{
                                        t('widgets.columns_count', {
                                            count: widget.config.columns,
                                        })
                                    }}
                                </span>
                                <span class="rounded bg-gray-100 px-2 py-1">
                                    {{
                                        t('widgets.up_to', {
                                            count: widget.config.limit,
                                        })
                                    }}
                                </span>
                                <span
                                    v-if="widget.config.featured_only"
                                    class="rounded bg-amber-50 px-2 py-1 text-amber-800"
                                >
                                    {{ t('widgets.featured_only_short') }}
                                </span>
                                <span
                                    v-if="widget.config.min_rating"
                                    class="rounded bg-amber-50 px-2 py-1 text-amber-800"
                                >
                                    {{
                                        t('widgets.stars_plus', {
                                            count: widget.config.min_rating,
                                        })
                                    }}
                                </span>
                            </div>

                            <pre
                                class="mt-4 overflow-x-auto rounded-md bg-gray-900 p-3 text-xs leading-relaxed text-gray-100"
                            ><code>{{ widget.embed_snippet }}</code></pre>
                        </div>

                        <div
                            class="flex items-center gap-4 border-t border-gray-100 bg-gray-50 px-6 py-3 text-sm"
                        >
                            <Link
                                :href="
                                    route('spaces.widgets.edit', [
                                        space.slug,
                                        widget.token,
                                    ])
                                "
                                class="text-gray-700 hover:text-gray-900"
                            >
                                {{ t('common.edit') }}
                            </Link>
                            <button
                                type="button"
                                class="text-indigo-600 hover:text-indigo-800"
                                @click="copySnippet(widget)"
                            >
                                {{
                                    copiedToken === widget.token
                                        ? t('common.copied')
                                        : t('widgets.copy_snippet')
                                }}
                            </button>
                            <a
                                :href="widget.embed_url"
                                target="_blank"
                                rel="noopener"
                                class="text-gray-700 hover:text-gray-900"
                            >
                                {{ t('widgets.open_script') }} ↗
                            </a>
                            <button
                                type="button"
                                class="ml-auto text-red-600 hover:text-red-800"
                                @click="destroy(widget)"
                            >
                                {{ t('common.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
