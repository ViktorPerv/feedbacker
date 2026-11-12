<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    spaces: {
        type: Array,
        default: () => [],
    },
    canViewAll: {
        type: Boolean,
        default: false,
    },
});

const { t, tc } = useTranslations();

const destroy = (space) => {
    if (confirm(t('spaces.delete_confirm', { name: space.name }))) {
        router.delete(route('spaces.destroy', space.slug));
    }
};

const copyLink = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
    } catch (e) {
        window.prompt(t('spaces.copy_prompt'), url);
    }
};
</script>

<template>
    <Head :title="t('spaces.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ t('spaces.title') }}
                    </h2>
                    <p v-if="canViewAll" class="mt-1 text-sm text-gray-500">
                        {{ t('spaces.oversight') }}
                    </p>
                </div>
                <Link :href="route('spaces.create')">
                    <PrimaryButton>{{ t('spaces.new') }}</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    v-if="spaces.length === 0"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="p-12 text-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ t('spaces.empty') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-500">
                            {{ t('spaces.empty_help') }}
                        </p>
                        <Link :href="route('spaces.create')" class="mt-6 inline-block">
                            <PrimaryButton>{{ t('spaces.create_first') }}</PrimaryButton>
                        </Link>
                    </div>
                </div>

                <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="space in spaces"
                        :key="space.id"
                        class="flex flex-col overflow-hidden bg-white shadow-sm sm:rounded-lg"
                    >
                        <div class="flex-1 p-6">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ space.name }}
                                </h3>
                                <span
                                    class="shrink-0 rounded-full px-2 py-1 text-xs font-medium"
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
                            </div>

                            <p class="mt-1 font-mono text-sm text-gray-500">
                                /c/{{ space.slug }}
                            </p>

                            <p
                                v-if="space.owner_name"
                                class="mt-2 inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800"
                            >
                                {{ t('spaces.owner') }}: {{ space.owner_name }}
                            </p>

                            <p v-if="space.custom_domain" class="mt-1 text-sm text-gray-500">
                                {{ space.custom_domain }}
                            </p>

                            <div class="mt-4 flex items-center gap-4 text-sm">
                                <span class="text-gray-500">
                                    {{
                                        tc(
                                            'testimonials.review_count',
                                            space.testimonials_count,
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="space.pending_count > 0"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                >
                                    {{
                                        t('spaces.pending_badge', {
                                            count: space.pending_count,
                                        })
                                    }}
                                </span>
                            </div>

                            <button
                                type="button"
                                class="mt-3 text-sm text-indigo-600 hover:text-indigo-800"
                                @click="copyLink(space.collection_url)"
                            >
                                {{ t('spaces.copy_link') }}
                            </button>
                        </div>

                        <div
                            class="flex items-center gap-4 border-t border-gray-100 bg-gray-50 px-6 py-3 text-sm"
                        >
                            <Link
                                :href="route('spaces.show', space.slug)"
                                class="text-gray-700 hover:text-gray-900"
                            >
                                {{ t('spaces.view') }}
                            </Link>

                            <Link
                                v-if="space.can.moderate"
                                :href="route('spaces.testimonials.index', space.slug)"
                                class="text-gray-700 hover:text-gray-900"
                            >
                                {{ t('spaces.inbox') }}
                            </Link>

                            <Link
                                v-if="space.can.update"
                                :href="route('spaces.edit', space.slug)"
                                class="text-gray-700 hover:text-gray-900"
                            >
                                {{ t('common.edit') }}
                            </Link>

                            <button
                                v-if="space.can.delete"
                                type="button"
                                class="ml-auto text-red-600 hover:text-red-800"
                                @click="destroy(space)"
                            >
                                {{ t('common.delete') }}
                            </button>

                            <span
                                v-else-if="!space.can.update"
                                class="ml-auto text-xs text-gray-400"
                            >
                                {{ t('spaces.read_only') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
