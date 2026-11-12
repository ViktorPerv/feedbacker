<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    translation: {
        type: Object,
        required: true,
    },
    languages: {
        type: Array,
        default: () => [],
    },
    siblings: {
        type: Object,
        default: () => ({}),
    },
});

const { t } = useTranslations();

// Only the wording is submitted. Language, group and key identify the entry
// and were fixed when it was created — see the hint in the form.
const form = useForm({
    value: props.translation.value ?? '',
});

const submit = () => {
    form.put(route('translations.update', props.translation.id), {
        preserveScroll: true,
    });
};

const destroy = () => {
    if (confirm(t('common.confirm_delete'))) {
        router.delete(route('translations.destroy', props.translation.id));
    }
};

const languageName = (code) =>
    props.languages.find((language) => language.code === code)?.native_name ?? code;
</script>

<template>
    <Head :title="`${t('common.edit')} — ${translation.key}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ t('translations.singular') }}
                    </h2>
                    <p class="mt-1 font-mono text-xs text-gray-500">
                        {{ translation.group }}.{{ translation.key }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('translations.index', { locale: translation.locale })"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        {{ t('common.back') }}
                    </Link>
                    <button
                        type="button"
                        class="text-xs font-semibold uppercase tracking-widest text-red-600 hover:text-red-800"
                        @click="destroy"
                    >
                        {{ t('common.delete') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <!-- Identity, fixed at creation -->
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ t('translations.identity') }}
                    </h3>

                    <dl class="mt-3 grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">
                                {{ t('translations.locale') }}
                            </dt>
                            <dd class="mt-0.5 text-sm text-gray-800">
                                {{ languageName(translation.locale) }}
                                <span class="text-gray-400">({{ translation.locale }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">
                                {{ t('translations.group') }}
                            </dt>
                            <dd class="mt-0.5 font-mono text-sm text-gray-800">
                                {{ translation.group }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">
                                {{ t('translations.key') }}
                            </dt>
                            <dd class="mt-0.5 font-mono text-sm text-gray-800">
                                {{ translation.key }}
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                        {{ t('translations.identity_hint') }}
                    </p>
                </div>

                <!-- Other languages -->
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ t('translations.siblings') }}
                    </h3>
                    <div
                        v-if="Object.keys(siblings).length === 0"
                        class="mt-2 text-sm text-gray-500"
                    >
                        {{ t('translations.no_siblings') }}
                    </div>
                    <dl v-else class="mt-3 space-y-2">
                        <div
                            v-for="(value, code) in siblings"
                            :key="code"
                            class="flex gap-3 text-sm"
                        >
                            <dt class="w-28 shrink-0 text-gray-500">
                                {{ languageName(code) }}
                            </dt>
                            <dd class="text-gray-800">{{ value }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- The editable part -->
                <form
                    class="space-y-6 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="value" :value="t('translations.value')" />
                        <textarea
                            id="value"
                            v-model="form.value"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p class="mt-1 text-xs text-gray-400">
                            {{ t('translations.source_hint') }}
                        </p>
                        <InputError class="mt-2" :message="form.errors.value" />
                    </div>

                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? t('common.saving') : t('common.save') }}
                        </button>
                        <span v-if="form.recentlySuccessful" class="text-sm text-green-600">
                            {{ t('translations.saved') }}
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
