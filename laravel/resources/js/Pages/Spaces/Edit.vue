<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SpaceForm from '@/Pages/Spaces/Partials/SpaceForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
});

const { t } = useTranslations();

const form = useForm({
    name: props.space.name,
    slug: props.space.slug,
    // Existing slugs stay as-is unless the user changes them.
    slugTouched: true,
    custom_domain: props.space.custom_domain ?? '',
    is_active: props.space.is_active,
});

const submit = () => {
    form.transform((data) => ({
        name: data.name,
        slug: data.slug,
        custom_domain: data.custom_domain || null,
        is_active: data.is_active,
    })).put(route('spaces.update', props.space.slug));
};
</script>

<template>
    <Head :title="t('spaces.edit_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('spaces.edit_title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <SpaceForm
                        :form="form"
                        :submit-label="t('spaces.save_changes')"
                        :cancel-href="route('spaces.show', space.slug)"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
