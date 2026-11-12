import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Substitute :placeholders in a translated string.
 */
function interpolate(line, replacements) {
    return Object.entries(replacements).reduce(
        (carry, [key, value]) => carry.replaceAll(`:${key}`, String(value)),
        line,
    );
}

/**
 * Pick a plural form.
 *
 * Laravel separates forms with `|`. Two forms is English; three is Russian,
 * where the choice depends on the last two digits — 1 звезда, 2 звезды,
 * 5 звёзд. Languages with other rules would need their own branch, but these
 * are the two the application ships.
 */
function pluralIndex(count, forms) {
    if (forms.length === 1) return 0;

    const n = Math.abs(count);

    if (forms.length === 2) return n === 1 ? 0 : 1;

    const mod10 = n % 10;
    const mod100 = n % 100;

    if (mod10 === 1 && mod100 !== 11) return 0;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 1;

    return 2;
}

/**
 * Interface translations, shared from the server.
 *
 * Every `app` group key arrives with each Inertia response, already resolved
 * for the current locale — including the database overrides and the file
 * fallback — so the frontend never fetches translations separately.
 */
export function useTranslations() {
    const page = usePage();

    const lines = computed(() => page.props.translations ?? {});

    /**
     * A translated string, or the key itself when it is missing.
     *
     * Returning the key rather than an empty string makes a gap visible in the
     * interface instead of silently rendering nothing.
     */
    const t = (key, replacements = {}) => {
        const line = lines.value[key];

        if (typeof line !== 'string') return key;

        return interpolate(line, replacements);
    };

    /**
     * A translated string with plural forms.
     */
    const tc = (key, count, replacements = {}) => {
        const line = lines.value[key];

        if (typeof line !== 'string') return key;

        const forms = line.split('|');
        const chosen = forms[pluralIndex(count, forms)] ?? forms[0];

        return interpolate(chosen.trim(), { count, ...replacements });
    };

    return {
        t,
        tc,
        locale: computed(() => page.props.locale ?? 'en'),
        locales: computed(() => page.props.locales ?? []),
    };
}
