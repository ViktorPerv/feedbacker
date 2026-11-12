<script setup>
import InputError from '@/Components/InputError.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    branding: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({ approved_count: 0, average_rating: 0 }),
    },
});

const page = usePage();
const { t, tc } = useTranslations();

const submitted = ref(false);
const hoveredStar = ref(0);

const form = useForm({
    author_name: '',
    author_email: '',
    author_title: '',
    author_company: '',
    rating: 5,
    content: '',
    video_url: '',
    // Honeypot — must stay empty.
    website: '',
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const theme = computed(() => ({
    '--brand': props.branding.primary_color,
    backgroundColor: props.branding.background_color,
    fontFamily: props.branding.font_family,
}));

const submit = () => {
    form.post(route('collect.store', props.space.slug), {
        preserveScroll: true,
        onSuccess: () => {
            if (!form.hasErrors) {
                submitted.value = true;
                form.reset('content', 'rating', 'video_url');
                form.rating = 5;
            }
        },
    });
};

const setRating = (value) => {
    form.rating = value;
};

const displayRating = computed(() => hoveredStar.value || form.rating);
</script>

<template>
    <Head :title="`${t('collect.your_testimonial')} — ${space.name}`" />

    <div class="min-h-screen px-4 py-12 sm:px-6 lg:px-8" :style="theme">
        <div class="mx-auto max-w-2xl">
            <!-- Brand header -->
            <div class="mb-8 text-center">
                <img
                    v-if="branding.logo_url"
                    :src="branding.logo_url"
                    :alt="space.name"
                    class="mx-auto mb-4 h-12 object-contain"
                />
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ space.name }}
                </h1>

                <p v-if="stats.approved_count > 0" class="mt-3 text-xs text-gray-500">
                    ★ {{ stats.average_rating }} ·
                    {{ tc('testimonials.stars', stats.approved_count) }}
                </p>

                <div class="mt-4 flex justify-center">
                    <LanguageSwitcher />
                </div>
            </div>

            <!-- Space closed -->
            <div
                v-if="!space.is_active"
                class="rounded-xl bg-white p-8 text-center shadow-sm"
            >
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ t('collect.closed') }}
                </h2>
            </div>

            <!-- Thank you -->
            <div
                v-else-if="submitted || flashSuccess"
                class="rounded-xl bg-white p-8 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full"
                    :style="{ backgroundColor: branding.primary_color }"
                >
                    <svg
                        class="h-6 w-6 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-gray-900">
                    {{ t('collect.thanks_title') }}
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ flashSuccess || branding.thank_you_message || t('collect.thanks_body') }}
                </p>
                <button
                    type="button"
                    class="mt-6 text-sm font-medium underline"
                    :style="{ color: branding.primary_color }"
                    @click="submitted = false"
                >
                    {{ t('collect.send_another') }}
                </button>
            </div>

            <!-- Form -->
            <form v-else class="rounded-xl bg-white p-6 shadow-sm sm:p-8" @submit.prevent="submit">
                <p
                    v-if="flashError"
                    class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ flashError }}
                </p>

                <!-- Rating -->
                <div class="text-center">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ t('collect.rating') }}
                    </label>
                    <div
                        class="mt-2 flex items-center justify-center gap-1"
                        @mouseleave="hoveredStar = 0"
                    >
                        <button
                            v-for="star in 5"
                            :key="star"
                            type="button"
                            class="text-3xl leading-none transition"
                            :style="{
                                color:
                                    star <= displayRating
                                        ? branding.primary_color
                                        : '#D1D5DB',
                            }"
                            :aria-label="tc('testimonials.stars', star)"
                            @mouseenter="hoveredStar = star"
                            @click="setRating(star)"
                        >
                            ★
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.rating" />
                </div>

                <div class="mt-6 space-y-5">
                    <div>
                        <label
                            for="author_name"
                            class="block text-sm font-medium text-gray-700"
                        >
                            {{ t('collect.your_name') }}
                            <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="author_name"
                            v-model="form.author_name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            placeholder="Jane Doe"
                        />
                        <InputError class="mt-2" :message="form.errors.author_name" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                for="author_title"
                                class="block text-sm font-medium text-gray-700"
                            >
                                {{ t('collect.your_title') }}
                            </label>
                            <input
                                id="author_title"
                                v-model="form.author_title"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                placeholder="Head of Growth"
                            />
                            <InputError class="mt-2" :message="form.errors.author_title" />
                        </div>

                        <div>
                            <label
                                for="author_company"
                                class="block text-sm font-medium text-gray-700"
                            >
                                {{ t('collect.your_company') }}
                            </label>
                            <input
                                id="author_company"
                                v-model="form.author_company"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                placeholder="Acme Inc."
                            />
                            <InputError class="mt-2" :message="form.errors.author_company" />
                        </div>
                    </div>

                    <div>
                        <label
                            for="author_email"
                            class="block text-sm font-medium text-gray-700"
                        >
                            {{ t('collect.your_email') }}
                        </label>
                        <input
                            id="author_email"
                            v-model="form.author_email"
                            type="email"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            placeholder="jane@example.com"
                        />
                        <InputError class="mt-2" :message="form.errors.author_email" />
                        <p class="mt-1 text-xs text-gray-400">
                            {{ t('collect.email_help') }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="content"
                            class="block text-sm font-medium text-gray-700"
                        >
                            {{ t('collect.your_testimonial') }}
                            <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="content"
                            v-model="form.content"
                            rows="5"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            :placeholder="t('collect.testimonial_placeholder')"
                        />
                        <InputError class="mt-2" :message="form.errors.content" />
                    </div>

                    <div>
                        <label
                            for="video_url"
                            class="block text-sm font-medium text-gray-700"
                        >
                            {{ t('collect.video_link') }}
                            <span class="text-xs text-gray-400">
                                ({{ t('common.optional') }})
                            </span>
                        </label>
                        <input
                            id="video_url"
                            v-model="form.video_url"
                            type="url"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            placeholder="https://www.youtube.com/watch?v=…"
                        />
                        <InputError class="mt-2" :message="form.errors.video_url" />
                        <p class="mt-1 text-xs text-gray-400">
                            {{ t('collect.video_help') }}
                        </p>
                    </div>

                    <!-- Honeypot: hidden from people, tempting to bots -->
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input
                            id="website"
                            v-model="form.website"
                            type="text"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    class="mt-8 w-full rounded-md px-6 py-3 text-sm font-semibold text-white shadow-sm transition disabled:opacity-50"
                    :style="{ backgroundColor: branding.primary_color }"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? t('collect.submitting')
                            : branding.button_text || t('collect.submit')
                    }}
                </button>

                <p class="mt-4 text-center text-xs text-gray-400">
                    Powered by Feedbacker
                </p>
            </form>
        </div>
    </div>
</template>
