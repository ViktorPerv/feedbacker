<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SpaceForm from '@/Pages/Spaces/Partials/SpaceForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, useForm } from '@inertiajs/vue3';

const { t } = useTranslations();

const form = useForm({
    name: '',
    slug: '',
    slugTouched: false,
    custom_domain: '',
    is_active: true,
});

const submit = () => {
    form.transform((data) => ({
        name: data.name,
        slug: data.slug,
        custom_domain: data.custom_domain || null,
        is_active: data.is_active,
    })).post(route('spaces.store'));
};
</script>

<template>
    <Head :title="t('spaces.new')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('spaces.new') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <SpaceForm
                        :form="form"
                        :submit-label="t('spaces.create_submit')"
                        :cancel-href="route('spaces.index')"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
