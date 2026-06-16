import { createI18n } from 'vue-i18n';
import en from './locales/en.json';

/**
 * Vue i18n instance. English only in v1; structure is future-ready for
 * additional locales without code changes. Never hardcode UI strings —
 * always go through translation keys.
 */
export const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: {
        en,
    },
});
