<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WidgetForm from '@/Pages/Spaces/Widgets/Partials/WidgetForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    widget: {
        type: Object,
        required: true,
    },
    layouts: {
        type: Array,
        required: true,
    },
    can: {
        type: Object,
        default: () => ({ delete: true }),
    },
});

const { t } = useTranslations();

const form = useForm({
    name: props.widget.name,
    layout: props.widget.layout,
    is_active: props.widget.is_active,
    config: { ...props.widget.config },
});

const submit = () => {
    form.put(
        route('spaces.widgets.update', [props.space.slug, props.widget.token]),
    );
};

const destroy = () => {
    if (confirm(t('widgets.delete_confirm', { name: props.widget.name }))) {
        router.delete(
            route('spaces.widgets.destroy', [
                props.space.slug,
                props.widget.token,
            ]),
        );
    }
};
</script>

<template>
    <Head :title="`${t('common.edit')} — ${widget.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ widget.name }}
                    </h2>
                    <p class="mt-1 font-mono text-xs text-gray-500">
                        {{ widget.token }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('spaces.widgets.index', space.slug)"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        {{ t('widgets.all') }}
                    </Link>
                    <button
                        v-if="can.delete"
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
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <WidgetForm
                        :form="form"
                        :layouts="layouts"
                        :embed-snippet="widget.embed_snippet"
                        :submit-label="t('spaces.save_changes')"
                        :cancel-href="route('spaces.widgets.index', space.slug)"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
