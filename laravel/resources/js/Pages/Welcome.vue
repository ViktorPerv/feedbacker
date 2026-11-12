<script setup>
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
    demoWidget: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const { t } = useTranslations();

const mobileMenuOpen = ref(false);

const isSignedIn = computed(() => Boolean(page.props.auth?.user));

/**
 * Load the real widget bundle.
 *
 * The snippet the product hands out is a <script> tag, and a literal one cannot
 * live in a Vue template — it would be parsed as the component's own script.
 * Injecting it on mount runs exactly the same code path a customer's site
 * would, which is the point of showing it here.
 */
onMounted(() => {
    if (!props.demoWidget || document.getElementById('feedbacker-demo')) {
        return;
    }

    const script = document.createElement('script');
    script.id = 'feedbacker-demo';
    script.src = props.demoWidget.script_url;
    script.async = true;
    document.body.appendChild(script);
});

// Titles and bodies are separate keys rather than arrays, so each string is a
// row in the translation catalogue that a translator can edit on its own.
const features = computed(() =>
    [1, 2, 3, 4, 5, 6].map((n) => ({
        title: t(`welcome.feature_${n}_title`),
        description: t(`welcome.feature_${n}_body`),
    })),
);

const steps = computed(() =>
    [1, 2, 3].map((n) => ({
        number: `0${n}`,
        title: t(`welcome.step_${n}_title`),
        description: t(`welcome.step_${n}_body`),
    })),
);

const stats = computed(() =>
    [1, 2, 3, 4].map((n) => ({
        value: t(`welcome.stat_${n}_value`),
        label: t(`welcome.stat_${n}_label`),
    })),
);

const embedPoints = computed(() => [1, 2, 3, 4].map((n) => t(`welcome.embed_${n}`)));
</script>

<template>
    <Head :title="t('welcome.meta_title')" />

    <div class="min-h-screen bg-white">
        <!-- ── Navigation ── -->
        <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/90 backdrop-blur">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-lg font-bold text-white"
                    >
                        F
                    </span>
                    <span class="text-lg font-semibold text-gray-900">Feedbacker</span>
                </Link>

                <div class="hidden items-center gap-8 md:flex">
                    <a href="#features" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ t('welcome.nav_features') }}
                    </a>
                    <a href="#how-it-works" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ t('welcome.nav_how') }}
                    </a>
                    <a href="#showcase" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ t('welcome.nav_widgets') }}
                    </a>
                    <a
                        v-if="demoWidget"
                        href="#demo"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        {{ t('welcome.nav_demo') }}
                    </a>
                </div>

                <div class="hidden items-center gap-3 md:flex">
                    <LanguageSwitcher />

                    <template v-if="isSignedIn">
                        <Link
                            :href="route('dashboard')"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                        >
                            {{ t('nav.dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="text-sm font-medium text-gray-600 hover:text-gray-900"
                        >
                            {{ t('nav.log_in') }}
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                        >
                            {{ t('welcome.start_free') }}
                        </Link>
                    </template>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 md:hidden"
                    :aria-label="t('nav.open_menu')"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>
            </nav>

            <div v-show="mobileMenuOpen" class="border-t border-gray-100 px-4 py-4 md:hidden">
                <div class="flex flex-col gap-3">
                    <a href="#features" class="text-sm text-gray-600">
                        {{ t('welcome.nav_features') }}
                    </a>
                    <a href="#how-it-works" class="text-sm text-gray-600">
                        {{ t('welcome.nav_how') }}
                    </a>
                    <a href="#showcase" class="text-sm text-gray-600">
                        {{ t('welcome.nav_widgets') }}
                    </a>
                    <a
                        v-if="demoWidget"
                        href="#demo"
                        class="text-sm text-gray-600"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t('welcome.nav_demo') }}
                    </a>

                    <div class="pt-1">
                        <LanguageSwitcher />
                    </div>

                    <template v-if="isSignedIn">
                        <Link
                            :href="route('dashboard')"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white"
                        >
                            {{ t('nav.dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link v-if="canLogin" :href="route('login')" class="text-sm text-gray-600">
                            {{ t('nav.log_in') }}
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white"
                        >
                            {{ t('welcome.start_free') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- ── Hero ── -->
        <section class="relative overflow-hidden bg-white">
            <div
                class="pointer-events-none absolute -top-24 right-0 h-96 w-96 rounded-full bg-indigo-50 blur-3xl"
            ></div>

            <div class="relative mx-auto max-w-7xl px-4 pb-20 pt-16 sm:px-6 lg:px-8 lg:pb-28 lg:pt-24">
                <div class="mx-auto max-w-3xl text-center">
                    <span
                        class="inline-flex items-center rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700"
                    >
                        {{ t('welcome.badge') }}
                    </span>

                    <h1
                        class="mt-6 text-4xl font-bold leading-tight tracking-tight text-gray-900 sm:text-5xl lg:text-6xl"
                    >
                        {{ t('welcome.hero_a') }}
                        <span class="text-indigo-600">{{ t('welcome.hero_highlight') }}</span>
                        {{ t('welcome.hero_b') }}
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-gray-600">
                        {{ t('welcome.hero_lead') }}
                    </p>

                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full rounded-md bg-indigo-600 px-6 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:w-auto"
                        >
                            {{ t('welcome.start_free') }}
                        </Link>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="w-full rounded-md border border-gray-300 bg-white px-6 py-3 text-center text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto"
                        >
                            {{ t('welcome.sign_in_account') }}
                        </Link>
                    </div>

                    <p class="mt-4 text-xs text-gray-400">
                        {{ t('welcome.free_note') }}
                    </p>
                </div>

                <!-- Stat strip -->
                <div
                    class="mx-auto mt-16 grid max-w-4xl grid-cols-2 gap-6 border-t border-gray-100 pt-10 sm:grid-cols-4"
                >
                    <div v-for="stat in stats" :key="stat.label" class="text-center">
                        <p class="text-3xl font-bold text-gray-900">{{ stat.value }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ stat.label }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Features ── -->
        <section id="features" class="border-t border-gray-100 bg-gray-50 py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                        {{ t('welcome.features_title') }}
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        {{ t('welcome.features_lead') }}
                    </p>
                </div>

                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50"
                        >
                            <span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">
                            {{ feature.title }}
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">
                            {{ feature.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── How it works ── -->
        <section id="how-it-works" class="bg-white py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                        {{ t('welcome.steps_title') }}
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        {{ t('welcome.steps_lead') }}
                    </p>
                </div>

                <div class="mt-16 grid gap-10 md:grid-cols-3">
                    <div v-for="step in steps" :key="step.number" class="relative">
                        <span class="text-5xl font-bold text-indigo-100">{{ step.number }}</span>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">
                            {{ step.title }}
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Showcase / embed ── -->
        <section id="showcase" class="border-t border-gray-100 bg-gray-900 py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                            {{ t('welcome.embed_title') }}
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-gray-300">
                            {{ t('welcome.embed_lead') }}
                        </p>

                        <ul class="mt-8 space-y-3">
                            <li
                                v-for="item in embedPoints"
                                :key="item"
                                class="flex items-start gap-3 text-sm text-gray-300"
                            >
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400"></span>
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-gray-700 bg-gray-950 p-6 shadow-2xl">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-red-500"></span>
                            <span class="h-3 w-3 rounded-full bg-yellow-500"></span>
                            <span class="h-3 w-3 rounded-full bg-green-500"></span>
                            <span class="ml-2 text-xs text-gray-500">index.html</span>
                        </div>
                        <pre
                            class="mt-4 overflow-x-auto text-xs leading-relaxed text-gray-300"
                        ><code>&lt;div data-feedbacker="your-slug"&gt;&lt;/div&gt;
&lt;script async
  src="http://localhost:8081/w/your-slug"&gt;
&lt;/script&gt;</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Live demo ── -->
        <section
            v-if="demoWidget"
            id="demo"
            class="border-t border-gray-100 bg-gray-50 py-20 lg:py-28"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <span
                        class="inline-flex items-center rounded-full border border-green-100 bg-green-50 px-3 py-1 text-xs font-medium text-green-700"
                    >
                        {{ t('welcome.demo_badge') }}
                    </span>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                        {{ t('welcome.demo_title') }}
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        {{ t('welcome.demo_lead', { space: demoWidget.space_name }) }}
                    </p>
                </div>

                <!--
                  The widget mounts itself into this element. Nothing else on
                  the page knows how it renders: this is the same snippet a
                  customer pastes into their own site.
                -->
                <div class="mt-12">
                    <div :data-feedbacker="demoWidget.token"></div>
                </div>

                <p class="mt-8 text-center text-xs text-gray-400">
                    {{ t('welcome.demo_note') }}
                </p>
            </div>
        </section>

        <!-- ── CTA ── -->
        <section class="bg-white py-20 lg:py-24">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                    {{ t('welcome.cta_title') }}
                </h2>
                <p class="mt-4 text-lg text-gray-600">
                    {{ t('welcome.cta_lead') }}
                </p>
                <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="w-full rounded-md bg-indigo-600 px-6 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:w-auto"
                    >
                        {{ t('welcome.cta_button') }}
                    </Link>
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="w-full rounded-md border border-gray-300 bg-white px-6 py-3 text-center text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto"
                    >
                        {{ t('nav.log_in') }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- ── Footer ── -->
        <footer class="border-t border-gray-100 bg-gray-50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white"
                        >
                            F
                        </span>
                        <span class="font-semibold text-gray-900">Feedbacker</span>
                    </div>

                    <p class="text-xs text-gray-500">
                        Laravel v{{ laravelVersion }} · PHP v{{ phpVersion }}
                    </p>
                </div>

                <p class="mt-8 text-center text-xs text-gray-400 sm:text-left">
                    {{ t('welcome.footer_note') }}
                </p>
            </div>
        </footer>
    </div>
</template>
