<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    languages: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
    defaults: {
        type: Object,
        default: () => ({ locale: 'en', group: 'app', key: '', value: '' }),
    },
});

const { t } = useTranslations();

const form = useForm({
    locale: props.defaults.locale,
    group: props.defaults.group,
    key: props.defaults.key,
    value: props.defaults.value,
});

const submit = () => {
    form.post(route('translations.store'));
};
</script>

<template>
    <Head :title="t('translations.new')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('translations.new') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="space-y-6 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="locale" :value="t('translations.locale')" />
                            <select
                                id="locale"
                                v-model="form.locale"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option
                                    v-for="language in languages"
                                    :key="language.code"
                                    :value="language.code"
                                >
                                    {{ language.native_name }} ({{ language.code }})
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.locale" />
                        </div>

                        <div>
                            <InputLabel for="group" :value="t('translations.group')" />
                            <input
                                id="group"
                                v-model="form.group"
                                type="text"
                                list="translation-groups"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <datalist id="translation-groups">
                                <option v-for="name in groups" :key="name" :value="name" />
                            </datalist>
                            <InputError class="mt-2" :message="form.errors.group" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="key" :value="t('translations.key')" />
                        <TextInput
                            id="key"
                            v-model="form.key"
                            type="text"
                            class="mt-1 block w-full font-mono"
                            placeholder="nav.dashboard"
                        />
                        <p class="mt-1 text-xs text-gray-400">
                            {{ t('translations.key_help') }}
                        </p>
                        <InputError class="mt-2" :message="form.errors.key" />
                    </div>

                    <div>
                        <InputLabel for="value" :value="t('translations.value')" />
                        <textarea
                            id="value"
                            v-model="form.value"
                            rows="3"
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
                        <Link
                            :href="route('translations.index')"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            {{ t('common.cancel') }}
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
