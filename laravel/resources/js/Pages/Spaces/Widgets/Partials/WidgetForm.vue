<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import WidgetPreview from '@/Pages/Spaces/Widgets/Partials/WidgetPreview.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    layouts: {
        type: Array,
        required: true,
    },
    submitLabel: {
        type: String,
        default: '',
    },
    cancelHref: {
        type: String,
        required: true,
    },
    // Editing an existing widget: the layout is still switchable, but the
    // token and snippet are already in the wild.
    embedSnippet: {
        type: String,
        default: null,
    },
});

defineEmits(['submit']);

const { t } = useTranslations();

const selectedLayout = computed(
    () =>
        props.layouts.find((item) => item.value === props.form.layout) ??
        props.layouts[0],
);

// Switching layout applies that layout's defaults for the options it owns, so
// the preview never shows a combination the renderer cannot produce.
watch(
    () => props.form.layout,
    (value, previous) => {
        if (!previous) return;

        const layout = props.layouts.find((item) => item.value === value);
        if (!layout) return;

        props.form.config.columns = layout.default_config.columns;
        props.form.config.limit = layout.default_config.limit;

        if (value === 'single') {
            props.form.config.featured_only = true;
        }
    },
);

const copySnippet = async () => {
    if (!props.embedSnippet) return;

    try {
        await navigator.clipboard.writeText(props.embedSnippet);
    } catch (e) {
        window.prompt(t('spaces.copy_prompt'), props.embedSnippet);
    }
};
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-5">
        <!-- ── Configuration ── -->
        <form class="space-y-6 lg:col-span-2" @submit.prevent="$emit('submit')">
            <div>
                <InputLabel for="name" :value="t('widgets.name')" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    placeholder="Homepage wall"
                />
                <InputError class="mt-2" :message="form.errors.name" />
                <p class="mt-1 text-xs text-gray-500">
                    {{ t('widgets.name_help') }}
                </p>
            </div>

            <div>
                <InputLabel :value="t('widgets.layout')" />
                <div class="mt-2 space-y-2">
                    <label
                        v-for="layout in layouts"
                        :key="layout.value"
                        class="flex cursor-pointer gap-3 rounded-lg border p-3 transition"
                        :class="
                            form.layout === layout.value
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 hover:bg-gray-50'
                        "
                    >
                        <input
                            v-model="form.layout"
                            type="radio"
                            :value="layout.value"
                            class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-900">
                                {{ t(`widgets.layout_${layout.value}`) }}
                            </span>
                            <span class="mt-0.5 block text-xs text-gray-500">
                                {{ t(`widgets.layout_${layout.value}_help`) }}
                            </span>
                        </span>
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.layout" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="theme" :value="t('widgets.theme')" />
                    <select
                        id="theme"
                        v-model="form.config.theme"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="light">{{ t('widgets.light') }}</option>
                        <option value="dark">{{ t('widgets.dark') }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors['config.theme']" />
                </div>

                <div v-if="selectedLayout?.supports_columns">
                    <InputLabel for="columns" :value="t('widgets.columns')" />
                    <TextInput
                        id="columns"
                        v-model.number="form.config.columns"
                        type="number"
                        min="1"
                        max="6"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors['config.columns']" />
                </div>

                <div v-if="selectedLayout?.supports_limit">
                    <InputLabel for="limit" :value="t('widgets.limit')" />
                    <TextInput
                        id="limit"
                        v-model.number="form.config.limit"
                        type="number"
                        min="1"
                        max="200"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors['config.limit']" />
                </div>
            </div>

            <div>
                <InputLabel for="accent" :value="t('widgets.accent')" />
                <div class="mt-1 flex items-center gap-3">
                    <input
                        id="accent"
                        v-model="form.config.accent"
                        type="color"
                        class="h-10 w-14 cursor-pointer rounded border border-gray-300"
                    />
                    <TextInput
                        v-model="form.config.accent"
                        type="text"
                        class="block w-full font-mono"
                        placeholder="#4F46E5"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors['config.accent']" />
                <p class="mt-1 text-xs text-gray-500">
                    {{ t('widgets.accent_help') }}
                </p>
            </div>

            <div>
                <InputLabel for="heading" :value="t('widgets.heading')" />
                <TextInput
                    id="heading"
                    v-model="form.config.heading"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="What our customers say"
                />
                <InputError class="mt-2" :message="form.errors['config.heading']" />
            </div>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-gray-700">
                    {{ t('widgets.filters') }}
                </legend>

                <div class="flex items-center gap-3">
                    <input
                        id="featured_only"
                        v-model="form.config.featured_only"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    />
                    <InputLabel for="featured_only" :value="t('widgets.featured_only')" />
                </div>

                <div>
                    <InputLabel for="min_rating" :value="t('widgets.min_rating')" />
                    <select
                        id="min_rating"
                        v-model="form.config.min_rating"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option :value="null">{{ t('widgets.any_rating') }}</option>
                        <option v-for="n in 5" :key="n" :value="n">
                            {{ t('widgets.stars_plus', { count: n }) }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors['config.min_rating']" />
                </div>
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-gray-700">
                    {{ t('widgets.display') }}
                </legend>

                <div class="flex items-center gap-3">
                    <input
                        id="ratings"
                        v-model="form.config.ratings"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    />
                    <InputLabel for="ratings" :value="t('widgets.ratings')" />
                </div>

                <div class="flex items-center gap-3">
                    <input
                        id="footer"
                        v-model="form.config.footer"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    />
                    <InputLabel for="footer" :value="t('widgets.footer')" />
                </div>

                <div class="flex items-center gap-3">
                    <input
                        id="is_active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    />
                    <InputLabel for="is_active" :value="t('widgets.active')" />
                </div>
            </fieldset>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 disabled:opacity-25"
                    :disabled="form.processing"
                >
                    {{ submitLabel || t('common.save') }}
                </button>
                <Link
                    :href="cancelHref"
                    class="text-sm text-gray-600 underline hover:text-gray-900"
                >
                    {{ t('common.cancel') }}
                </Link>
            </div>
        </form>

        <!-- ── Preview ── -->
        <div class="lg:col-span-3">
            <div class="sticky top-6 space-y-4">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ t('widgets.preview') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('widgets.preview_help') }}
                    </p>

                    <div class="mt-4">
                        <WidgetPreview :layout="form.layout" :config="form.config" />
                    </div>
                </div>

                <div
                    v-if="embedSnippet"
                    class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ t('widgets.embed') }}
                    </h3>
                    <pre
                        class="mt-3 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs leading-relaxed text-gray-100"
                    ><code>{{ embedSnippet }}</code></pre>
                    <button
                        type="button"
                        class="mt-3 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                        @click="copySnippet"
                    >
                        {{ t('widgets.copy_snippet') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
