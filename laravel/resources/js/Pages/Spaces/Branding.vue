<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    branding: {
        type: Object,
        required: true,
    },
});

const { t } = useTranslations();

const form = useForm({
    logo_url: props.branding.logo_url ?? '',
    primary_color: props.branding.primary_color ?? '#4F46E5',
    background_color: props.branding.background_color ?? '#FFFFFF',
    font_family: props.branding.font_family ?? 'Inter',
    button_text: props.branding.button_text ?? '',
    thank_you_message: props.branding.thank_you_message ?? '',
});

const fonts = [
    'Inter',
    'Figtree',
    'Roboto',
    'Open Sans',
    'Lato',
    'Georgia',
    'system-ui',
];

const previewStyle = computed(() => ({
    backgroundColor: form.background_color,
    fontFamily: form.font_family,
}));

const submit = () => {
    form.transform((data) => ({
        ...data,
        logo_url: data.logo_url || null,
    })).put(route('spaces.branding.update', props.space.slug));
};

// ── Logo upload ──
//
// The stored value is still a URL, so an upload and a pasted link are
// interchangeable. Uploads go to their own endpoint because they are
// multipart while the rest of the form is JSON.

const logoInput = ref(null);
const uploading = ref(false);

const uploadLogo = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    uploading.value = true;

    router.post(
        route('spaces.logo.store', props.space.slug),
        { logo: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploading.value = false;
                if (logoInput.value) logoInput.value.value = '';
            },
        },
    );
};

const removeLogo = () => {
    router.delete(route('spaces.logo.destroy', props.space.slug), {
        preserveScroll: true,
    });
};

// The upload round-trips through the server, so the form has to pick the new
// URL up from the refreshed props.
watch(
    () => props.branding.logo_url,
    (value) => {
        form.logo_url = value ?? '';
    },
);
</script>

<template>
    <Head :title="`${t('branding.title')} — ${space.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('branding.title') }} — {{ space.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-3">
                    <div
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2"
                    >
                        <form class="space-y-6" @submit.prevent="submit">
                            <div>
                                <InputLabel :value="t('branding.logo')" />
                                <div class="mt-2 flex flex-wrap items-center gap-4">
                                    <img
                                        v-if="form.logo_url"
                                        :src="form.logo_url"
                                        alt=""
                                        class="h-14 w-14 rounded-md object-contain ring-1 ring-gray-200"
                                    />
                                    <div
                                        v-else
                                        class="flex h-14 w-14 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400"
                                    >
                                        {{ t('common.none') }}
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
                                            :disabled="uploading"
                                            @click="logoInput?.click()"
                                        >
                                            {{
                                                uploading
                                                    ? t('branding.uploading')
                                                    : t('branding.upload_image')
                                            }}
                                        </button>
                                        <button
                                            v-if="form.logo_url"
                                            type="button"
                                            class="text-xs font-semibold uppercase tracking-widest text-red-600 hover:text-red-800"
                                            @click="removeLogo"
                                        >
                                            {{ t('branding.remove') }}
                                        </button>
                                    </div>

                                    <input
                                        ref="logoInput"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="hidden"
                                        @change="uploadLogo"
                                    />
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ t('branding.logo_help') }}
                                </p>
                            </div>

                            <div>
                                <InputLabel for="logo_url" :value="t('branding.logo_url')" />
                                <TextInput
                                    id="logo_url"
                                    v-model="form.logo_url"
                                    type="url"
                                    class="mt-1 block w-full"
                                    placeholder="https://example.com/logo.png"
                                />
                                <InputError class="mt-2" :message="form.errors.logo_url" />
                            </div>

                            <div class="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <InputLabel
                                        for="primary_color"
                                        :value="t('branding.primary_color')"
                                    />
                                    <div class="mt-1 flex items-center gap-3">
                                        <input
                                            id="primary_color"
                                            v-model="form.primary_color"
                                            type="color"
                                            class="h-10 w-14 cursor-pointer rounded border border-gray-300"
                                        />
                                        <TextInput
                                            v-model="form.primary_color"
                                            type="text"
                                            class="block w-full font-mono"
                                        />
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.primary_color"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        for="background_color"
                                        :value="t('branding.background_color')"
                                    />
                                    <div class="mt-1 flex items-center gap-3">
                                        <input
                                            id="background_color"
                                            v-model="form.background_color"
                                            type="color"
                                            class="h-10 w-14 cursor-pointer rounded border border-gray-300"
                                        />
                                        <TextInput
                                            v-model="form.background_color"
                                            type="text"
                                            class="block w-full font-mono"
                                        />
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.background_color"
                                    />
                                </div>
                            </div>

                            <div>
                                <InputLabel
                                    for="font_family"
                                    :value="t('branding.font_family')"
                                />
                                <select
                                    id="font_family"
                                    v-model="form.font_family"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="font in fonts" :key="font" :value="font">
                                        {{ font }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.font_family" />
                            </div>

                            <div>
                                <InputLabel
                                    for="button_text"
                                    :value="t('branding.button_text')"
                                />
                                <TextInput
                                    id="button_text"
                                    v-model="form.button_text"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors.button_text" />
                            </div>

                            <div>
                                <InputLabel
                                    for="thank_you_message"
                                    :value="t('branding.thank_you')"
                                />
                                <textarea
                                    id="thank_you_message"
                                    v-model="form.thank_you_message"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.thank_you_message"
                                />
                            </div>

                            <div class="flex items-center gap-4">
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 disabled:opacity-25"
                                    :disabled="form.processing"
                                >
                                    {{ t('branding.save') }}
                                </button>
                                <Link
                                    :href="route('spaces.show', space.slug)"
                                    class="text-sm text-gray-600 underline hover:text-gray-900"
                                >
                                    {{ t('spaces.back_to_space') }}
                                </Link>
                            </div>
                        </form>
                    </div>

                    <div class="lg:col-span-1">
                        <div
                            class="sticky top-6 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                        >
                            <h3 class="text-base font-semibold text-gray-900">
                                {{ t('branding.preview_title') }}
                            </h3>
                            <div
                                class="mt-4 rounded-lg border border-gray-200 p-6"
                                :style="previewStyle"
                            >
                                <div v-if="form.logo_url" class="mb-4">
                                    <img
                                        :src="form.logo_url"
                                        alt=""
                                        class="h-10 object-contain"
                                    />
                                </div>
                                <p class="text-sm" :style="{ color: form.primary_color }">
                                    {{ form.thank_you_message }}
                                </p>
                                <button
                                    type="button"
                                    class="mt-4 rounded-md px-4 py-2 text-sm font-medium text-white"
                                    :style="{ backgroundColor: form.primary_color }"
                                >
                                    {{ form.button_text }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
