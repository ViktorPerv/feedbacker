<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    testimonials: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ status: '', search: '', tag: '', rating: null }),
    },
    counts: {
        type: Object,
        default: () => ({ all: 0, pending: 0, approved: 0, rejected: 0 }),
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
});

const { t, tc } = useTranslations();

const search = ref(props.filters.search ?? '');
const rating = ref(props.filters.rating ?? '');

// One draft per testimonial, so typing in one card does not disturb another.
const tagDrafts = reactive({});

let searchTimer = null;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ search: value }), 350);
});

watch(rating, (value) => applyFilters({ rating: value }));

const applyFilters = (overrides = {}) => {
    router.get(
        route('spaces.testimonials.index', props.space.slug),
        {
            status: props.filters.status || undefined,
            search: search.value || undefined,
            tag: props.filters.tag || undefined,
            rating: rating.value || undefined,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const setStatus = (status) => applyFilters({ status: status || undefined });

const setTag = (tag) => applyFilters({ tag: tag || undefined });

const act = (testimonial, action) => {
    const routes = {
        approve: 'testimonials.approve',
        reject: 'testimonials.reject',
        feature: 'testimonials.feature',
    };

    router.patch(route(routes[action], testimonial.id), {}, { preserveScroll: true });
};

const destroy = (testimonial) => {
    if (confirm(t('testimonials.delete_confirm', { name: testimonial.author_name }))) {
        router.delete(route('testimonials.destroy', testimonial.id), {
            preserveScroll: true,
        });
    }
};

// ── Tags ──

const saveTags = (testimonial, tags) => {
    router.patch(
        route('testimonials.tags', testimonial.id),
        { tags },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                tagDrafts[testimonial.id] = '';
            },
        },
    );
};

const addTag = (testimonial) => {
    const draft = (tagDrafts[testimonial.id] ?? '').trim().toLowerCase();

    if (!draft) return;
    if (testimonial.tags.includes(draft)) {
        tagDrafts[testimonial.id] = '';
        return;
    }

    saveTags(testimonial, [...testimonial.tags, draft]);
};

const removeTag = (testimonial, tag) => {
    saveTags(
        testimonial,
        testimonial.tags.filter((existing) => existing !== tag),
    );
};

const statusClasses = (status) =>
    ({
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-gray-200 text-gray-600',
    })[status] ?? 'bg-gray-100 text-gray-600';

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : '—');
</script>

<template>
    <Head :title="`${t('testimonials.title')} — ${space.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ t('testimonials.title') }} — {{ space.name }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{
                            t('testimonials.awaiting_moderation', {
                                count: counts.pending,
                            })
                        }}
                        ·
                        {{ t('testimonials.approved_count', { count: counts.approved }) }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a
                        :href="space.collection_url"
                        target="_blank"
                        rel="noopener"
                        class="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        {{ t('testimonials.open_collection') }} ↗
                    </a>
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
                <!-- Filters -->
                <div class="space-y-4 overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full px-3 py-1 text-sm font-medium transition"
                                :class="
                                    !filters.status
                                        ? 'bg-gray-900 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                "
                                @click="setStatus('')"
                            >
                                {{ t('common.all') }} ({{ counts.all }})
                            </button>
                            <button
                                v-for="status in statuses"
                                :key="status.value"
                                type="button"
                                class="rounded-full px-3 py-1 text-sm font-medium transition"
                                :class="
                                    filters.status === status.value
                                        ? 'bg-gray-900 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                "
                                @click="setStatus(status.value)"
                            >
                                {{ t(`testimonials.${status.value}`) }}
                                ({{ counts[status.value] }})
                            </button>
                        </div>

                        <div class="ml-auto flex items-center gap-3">
                            <select
                                v-model="rating"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">{{ t('testimonials.any_rating') }}</option>
                                <option v-for="n in 5" :key="n" :value="n">
                                    {{ tc('testimonials.stars', n) }}
                                </option>
                            </select>

                            <input
                                v-model="search"
                                type="search"
                                :placeholder="t('testimonials.search_placeholder')"
                                class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Tag filter -->
                    <div
                        v-if="availableTags.length > 0"
                        class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3"
                    >
                        <span
                            class="text-xs font-medium uppercase tracking-wide text-gray-400"
                        >
                            {{ t('testimonials.tags') }}
                        </span>
                        <button
                            v-for="item in availableTags"
                            :key="item.tag"
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                            :class="
                                filters.tag === item.tag
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
                            "
                            @click="setTag(filters.tag === item.tag ? '' : item.tag)"
                        >
                            {{ item.tag }}
                            <span class="opacity-60">{{ item.count }}</span>
                        </button>
                        <button
                            v-if="filters.tag"
                            type="button"
                            class="text-xs text-gray-500 underline hover:text-gray-800"
                            @click="setTag('')"
                        >
                            {{ t('common.clear') }}
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="testimonials.data.length === 0"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="p-12 text-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ t('testimonials.empty') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-500">
                            {{
                                filters.search || filters.tag || filters.status
                                    ? t('testimonials.empty_filtered')
                                    : t('testimonials.empty_all')
                            }}
                        </p>
                        <a
                            v-if="!filters.search && !filters.tag && !filters.status"
                            :href="space.collection_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                        >
                            {{ space.collection_url }}
                        </a>
                    </div>
                </div>

                <!-- List -->
                <div v-else class="space-y-4">
                    <article
                        v-for="testimonial in testimonials.data"
                        :key="testimonial.id"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <img
                                    v-if="testimonial.author_avatar_url"
                                    :src="testimonial.author_avatar_url"
                                    :alt="testimonial.author_name"
                                    class="h-10 w-10 shrink-0 rounded-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white"
                                >
                                    {{ testimonial.initials }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-gray-900">
                                            {{ testimonial.author_name }}
                                        </span>
                                        <span
                                            v-if="testimonial.author_title"
                                            class="text-sm text-gray-500"
                                        >
                                            {{ testimonial.author_title }}
                                        </span>
                                        <span
                                            v-if="testimonial.author_company"
                                            class="text-sm text-gray-400"
                                        >
                                            · {{ testimonial.author_company }}
                                        </span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="statusClasses(testimonial.status)"
                                        >
                                            {{
                                                t(`testimonials.${testimonial.status}`)
                                            }}
                                        </span>
                                        <span
                                            v-if="testimonial.is_featured"
                                            class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
                                        >
                                            {{ t('testimonials.featured') }}
                                        </span>
                                        <a
                                            v-if="testimonial.video_url"
                                            :href="testimonial.video_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="rounded-full bg-gray-900 px-2 py-0.5 text-xs font-medium text-white hover:bg-gray-700"
                                        >
                                            ▶ {{ t('testimonials.video') }}
                                        </a>
                                    </div>

                                    <div class="mt-1 flex items-center gap-3">
                                        <span
                                            class="text-sm text-amber-500"
                                            :aria-label="
                                                tc('testimonials.stars', testimonial.rating)
                                            "
                                        >
                                            {{ '★'.repeat(testimonial.rating)
                                            }}{{ '☆'.repeat(5 - testimonial.rating) }}
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            {{ formatDate(testimonial.created_at) }}
                                        </span>
                                        <span
                                            v-if="testimonial.source !== 'direct'"
                                            class="text-xs text-gray-400"
                                        >
                                            {{ t('testimonials.via') }}
                                            {{ testimonial.source }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-700"
                                    >
                                        {{ testimonial.content }}
                                    </p>

                                    <p
                                        v-if="testimonial.author_email"
                                        class="mt-2 text-xs text-gray-400"
                                    >
                                        {{ testimonial.author_email }}
                                    </p>

                                    <!-- Tags -->
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <span
                                            v-for="tag in testimonial.tags"
                                            :key="tag"
                                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                                        >
                                            <button
                                                type="button"
                                                class="hover:text-indigo-700"
                                                :title="
                                                    t('testimonials.filter_by_tag', { tag })
                                                "
                                                @click="setTag(tag)"
                                            >
                                                {{ tag }}
                                            </button>
                                            <button
                                                type="button"
                                                class="text-gray-400 hover:text-red-600"
                                                :aria-label="
                                                    t('testimonials.remove_tag', { tag })
                                                "
                                                @click="removeTag(testimonial, tag)"
                                            >
                                                ×
                                            </button>
                                        </span>

                                        <input
                                            v-model="tagDrafts[testimonial.id]"
                                            type="text"
                                            :placeholder="t('testimonials.add_tag')"
                                            maxlength="32"
                                            class="w-28 rounded-md border-gray-200 px-2 py-0.5 text-xs shadow-sm focus:border-indigo-400 focus:ring-indigo-400"
                                            @keydown.enter.prevent="addTag(testimonial)"
                                        />
                                        <button
                                            v-if="tagDrafts[testimonial.id]"
                                            type="button"
                                            class="text-xs text-indigo-600 hover:text-indigo-800"
                                            @click="addTag(testimonial)"
                                        >
                                            {{ t('testimonials.add') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-3 border-t border-gray-100 bg-gray-50 px-6 py-3 text-sm"
                        >
                            <button
                                v-if="testimonial.status !== 'approved'"
                                type="button"
                                class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-green-700"
                                @click="act(testimonial, 'approve')"
                            >
                                {{ t('testimonials.approve') }}
                            </button>
                            <button
                                v-if="testimonial.status !== 'rejected'"
                                type="button"
                                class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-700 ring-1 ring-gray-300 transition hover:bg-gray-100"
                                @click="act(testimonial, 'reject')"
                            >
                                {{ t('testimonials.reject') }}
                            </button>
                            <button
                                v-if="testimonial.status === 'approved'"
                                type="button"
                                class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-700 ring-1 ring-indigo-300 transition hover:bg-indigo-50"
                                @click="act(testimonial, 'feature')"
                            >
                                {{
                                    testimonial.is_featured
                                        ? t('testimonials.unfeature')
                                        : t('testimonials.feature')
                                }}
                            </button>

                            <button
                                type="button"
                                class="ml-auto text-xs font-semibold uppercase tracking-wide text-red-600 hover:text-red-800"
                                @click="destroy(testimonial)"
                            >
                                {{ t('common.delete') }}
                            </button>
                        </div>
                    </article>

                    <!-- Pagination -->
                    <div v-if="testimonials.links.length > 3" class="flex flex-wrap gap-1">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="(link, index) in testimonials.links"
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
        </div>
    </AuthenticatedLayout>
</template>
