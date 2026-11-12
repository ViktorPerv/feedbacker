<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WidgetForm from '@/Pages/Spaces/Widgets/Partials/WidgetForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    layouts: {
        type: Array,
        required: true,
    },
});

const { t } = useTranslations();

const initial = computed(
    () =>
        props.layouts.find((item) => item.value === 'masonry')?.default_config ??
        props.layouts[0].default_config,
);

const form = useForm({
    name: '',
    layout: 'masonry',
    is_active: true,
    config: { ...initial.value },
});

const submit = () => {
    form.post(route('spaces.widgets.store', props.space.slug));
};
</script>

<template>
    <Head :title="`${t('widgets.new')} — ${space.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('widgets.new') }} — {{ space.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <WidgetForm
                        :form="form"
                        :layouts="layouts"
                        :submit-label="t('widgets.create_submit')"
                        :cancel-href="route('spaces.widgets.index', space.slug)"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
