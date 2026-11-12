<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    // Not named `translations`: that would shadow the shared translation
    // map and every t() call would render the raw key.
    entries: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ locale: 'en', group: '', search: '', missing: false }),
    },
    languages: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Array,
        default: () => [],
    },
});

const { t } = useTranslations();

const search = ref(props.filters.search ?? '');
const group = ref(props.filters.group ?? '');
const missing = ref(props.filters.missing ?? false);

const apply = (overrides = {}) => {
    router.get(
        route('translations.index'),
        {
            locale: props.filters.locale,
            group: group.value || undefined,
            search: search.value || undefined,
            missing: missing.value ? 1 : undefined,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => apply(), 350);
});

watch([group, missing], () => apply());

const setLocale = (code) => apply({ locale: code });

const runImport = () => {
    router.post(route('translations.import'), {}, { preserveScroll: true });
};

const destroy = (translation) => {
    if (confirm(t('common.confirm_delete'))) {
        router.delete(route('translations.destroy', translation.id), {
            preserveScroll: true,
        });
    }
};

const percentClass = (percent) => {
    if (percent >= 95) return 'bg-green-500';
    if (percent >= 50) return 'bg-amber-500';
    return 'bg-red-500';
};
</script>

<template>
    <Head :title="t('translations.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ t('translations.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('translations.import_help') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                        @click="runImport"
                    >
                        {{ t('translations.import') }}
                    </button>
                    <Link :href="route('translations.create')">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                        >
                            {{ t('translations.new') }}
                        </button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Completion -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="stat in stats"
                        :key="stat.code"
                        class="overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg"
                    >
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-medium text-gray-900">
                                {{ stat.native_name }}
                                <span class="text-gray-400">({{ stat.code }})</span>
                            </span>
                            <span class="text-sm text-gray-500">
                                {{ stat.percent }}%
                            </span>
                        </div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="percentClass(stat.percent)"
                                :style="{ width: `${stat.percent}%` }"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-400">
                            {{
                                t('translations.progress', {
                                    translated: stat.translated,
                                    total: stat.total,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="space-y-4 overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="language in languages"
                                :key="language.code"
                                type="button"
                                class="rounded-full px-3 py-1 text-sm font-medium transition"
                                :class="
                                    filters.locale === language.code
                                        ? 'bg-gray-900 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                "
                                @click="setLocale(language.code)"
                            >
                                {{ language.native_name }}
                            </button>
                        </div>

                        <div class="ml-auto flex flex-wrap items-center gap-3">
                            <select
                                v-model="group"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">{{ t('common.all') }}</option>
                                <option v-for="name in groups" :key="name" :value="name">
                                    {{ name }}
                                </option>
                            </select>

                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    v-model="missing"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                {{ t('translations.only_missing') }}
                            </label>

                            <input
                                v-model="search"
                                type="search"
                                :placeholder="t('translations.search_placeholder')"
                                class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div
                        v-if="entries.data.length === 0"
                        class="p-12 text-center"
                    >
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ t('translations.empty') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-500">
                            {{ t('translations.empty_hint') }}
                        </p>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    {{ t('translations.key') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    {{ t('translations.value') }}
                                </th>
                                <th class="w-32 px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="translation in entries.data"
                                :key="translation.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-4 py-3 align-top">
                                    <span class="block text-xs text-gray-400">
                                        {{ translation.group }}
                                    </span>
                                    <span class="font-mono text-sm text-gray-900">
                                        {{ translation.key }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span
                                        v-if="translation.value"
                                        class="text-sm text-gray-700"
                                    >
                                        {{ translation.value }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                    >
                                        {{ t('translations.only_missing') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right align-top text-sm whitespace-nowrap">
                                    <Link
                                        :href="route('translations.edit', translation.id)"
                                        class="text-gray-700 hover:text-gray-900"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        type="button"
                                        class="ml-3 text-red-600 hover:text-red-800"
                                        @click="destroy(translation)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="entries.links && entries.links.length > 3"
                    class="flex flex-wrap gap-1"
                >
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, index) in entries.links"
                        :key="index"
                        :href="link.url ?? undefined"
                        class="rounded-md px-3 py-1 text-sm"
                        :class="[
                            link.active
                                ? 'bg-gray-900 text-white'
                                : 'bg-white text-gray-700 ring-1 ring-gray-300',
                            !link.url ? 'cursor-default opacity-50' : 'hover:bg-gray-100',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
