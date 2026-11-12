<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    spacesCount: {
        type: Number,
        default: 0,
    },
    testimonialsCount: {
        type: Number,
        default: 0,
    },
    pendingCount: {
        type: Number,
        default: 0,
    },
    canViewAll: {
        type: Boolean,
        default: false,
    },
});

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('dashboard.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-3">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">
                                {{ t('dashboard.spaces') }}
                            </h3>
                            <p class="mt-2 text-3xl font-semibold text-gray-900">
                                {{ spacesCount }}
                            </p>
                            <Link
                                :href="route('spaces.index')"
                                class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                {{ t('dashboard.manage_spaces') }} →
                            </Link>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">
                                {{ t('dashboard.testimonials') }}
                            </h3>
                            <p class="mt-2 text-3xl font-semibold text-gray-900">
                                {{ testimonialsCount }}
                            </p>
                            <p class="mt-4 text-sm text-gray-400">
                                {{
                                    canViewAll
                                        ? t('dashboard.across_accounts')
                                        : t('dashboard.across_all')
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">
                                {{ t('dashboard.pending') }}
                            </h3>
                            <p
                                class="mt-2 text-3xl font-semibold"
                                :class="
                                    pendingCount > 0 ? 'text-amber-600' : 'text-gray-900'
                                "
                            >
                                {{ pendingCount }}
                            </p>
                            <Link
                                v-if="spacesCount > 0"
                                :href="route('spaces.index')"
                                class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                            >
                                {{ t('dashboard.open_inbox') }} →
                            </Link>
                            <p v-else class="mt-4 text-sm text-gray-400">
                                {{ t('dashboard.create_first') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="spacesCount === 0"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="p-8 text-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ t('dashboard.get_started') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-500">
                            {{ t('dashboard.get_started_lead') }}
                        </p>
                        <Link
                            :href="route('spaces.create')"
                            class="mt-6 inline-block rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                        >
                            {{ t('dashboard.create_space') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
