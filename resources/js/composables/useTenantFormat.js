import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Dates, times and money in the COMPANY's settings — not the browser's.
 *
 * A Perth workspace must not see Sydney times just because the staff member's
 * laptop is set to Sydney (or to nothing in particular). Every date the app
 * renders should go through here so one setting changes them all.
 *
 *   const { date, dateTime, time, money } = useTenantFormat();
 *   date(vehicle.registration_expiry)  // 03/07/2026
 *   dateTime(log.created_at)           // 03/07/2026, 9:14 am
 *
 * Date-ONLY values (an expiry date, an expense date) are rendered as written —
 * shifting them by a timezone would change the day, which is never wanted.
 */
export function useTenantFormat() {
    const page = usePage();

    const timeZone = computed(() => page.props.tenant?.timezone || 'Australia/Sydney');
    const currency = computed(() => page.props.tenant?.currency || 'AUD');
    // 'd/m/Y' | 'd M Y' | 'Y-m-d' — the company's chosen date style.
    const pattern = computed(() => page.props.tenant?.date_format || 'd/m/Y');

    const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/;

    function parts(value, options) {
        return new Intl.DateTimeFormat('en-AU', { timeZone: timeZone.value, ...options }).format(value);
    }

    /** Render a Y-m-d value without touching the timezone. */
    function plainDate(iso) {
        const [y, m, d] = iso.split('-');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        if (pattern.value === 'Y-m-d') return iso;
        if (pattern.value === 'd M Y') return `${d} ${months[Number(m) - 1]} ${y}`;
        return `${d}/${m}/${y}`;
    }

    function toDate(value) {
        if (!value) return null;
        if (value instanceof Date) return value;
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    /** A date, in the company's format and timezone. */
    function date(value) {
        if (!value) return null;

        const raw = String(value);
        // Date-only strings (and the date half of a timestamp with no time)
        // are shown as stored.
        if (DATE_ONLY.test(raw)) return plainDate(raw);
        if (DATE_ONLY.test(raw.slice(0, 10)) && raw.includes('00:00:00')) return plainDate(raw.slice(0, 10));

        const parsed = toDate(value);
        if (!parsed) return null;

        const iso = parts(parsed, { year: 'numeric', month: '2-digit', day: '2-digit' })
            .split('/')
            .reverse()
            .join('-');

        return plainDate(iso);
    }

    /** A date and time, in the company's timezone. */
    function dateTime(value) {
        const parsed = toDate(value);
        if (!parsed) return null;

        return `${date(parsed)}, ${time(parsed)}`;
    }

    function time(value) {
        const parsed = toDate(value);
        if (!parsed) return null;

        return parts(parsed, { hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();
    }

    /** Cents → the company's currency. */
    function money(cents) {
        return new Intl.NumberFormat('en-AU', {
            style: 'currency',
            currency: currency.value,
        }).format((Number(cents) || 0) / 100);
    }

    return { date, dateTime, time, money, timeZone, currency, pattern };
}
