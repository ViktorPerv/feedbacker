<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Link } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
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
    // When true, editing an existing space keeps the slug stable unless the
    // user edits it explicitly.
    autoSlug: {
        type: Boolean,
        default: true,
    },
});

const { t } = useTranslations();

const slugify = (value) =>
    value
        .toString()
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');

// Mirror the name into the slug until the user touches the slug field.
watch(
    () => props.form.name,
    (name) => {
        if (props.autoSlug && !props.form.slugTouched) {
            props.form.slug = slugify(name || '');
        }
    },
);
</script>

<template>
    <form @submit.prevent="$emit('submit')">
        <div class="space-y-6">
            <div>
                <InputLabel for="name" :value="t('spaces.name')" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    placeholder="Acme Inc."
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="slug" :value="t('spaces.slug_label')" />
                <div class="mt-1 flex rounded-md shadow-sm">
                    <span
                        class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500"
                    >
                        /c/
                    </span>
                    <TextInput
                        id="slug"
                        v-model="form.slug"
                        type="text"
                        class="block w-full rounded-l-none"
                        required
                        placeholder="acme-inc"
                        @input="form.slugTouched = true"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.slug" />
                <p class="mt-1 text-xs text-gray-500">
                    {{ t('spaces.slug_help') }}
                </p>
            </div>

            <div>
                <InputLabel for="custom_domain" :value="t('spaces.custom_domain_label')" />
                <TextInput
                    id="custom_domain"
                    v-model="form.custom_domain"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="reviews.example.com"
                />
                <InputError class="mt-2" :message="form.errors.custom_domain" />
            </div>

            <div class="flex items-center gap-3">
                <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                />
                <InputLabel for="is_active" :value="t('spaces.is_active_label')" />
                <InputError class="mt-2" :message="form.errors.is_active" />
            </div>
        </div>

        <div class="mt-8 flex items-center gap-4">
            <button
                type="submit"
                class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
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
</template>
