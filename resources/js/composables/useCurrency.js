// Shared currency helpers. All monetary values in DVARO are stored as integer
// cents (CLAUDE.md); these convert to/from a display string. Reusable across
// Agreement, Invoice, Customer pages.
//
// Default currency is AUD (the platform default / Australian market). The
// currency code is a parameter so multi-currency tenants are supported later.

export function useCurrency(currency = 'AUD', locale = 'en-AU') {
    const formatter = new Intl.NumberFormat(locale, { style: 'currency', currency });

    // cents (int) → "$1,234.56"
    function formatAUD(cents) {
        return formatter.format((Number(cents) || 0) / 100);
    }

    // dollars input (string|number) → integer cents, e.g. "12.5" → 1250
    function toCents(dollars) {
        return Math.round((Number(dollars) || 0) * 100);
    }

    // integer cents → dollars number for an <input>, e.g. 1250 → 12.5
    function toDollars(cents) {
        return (Number(cents) || 0) / 100;
    }

    return { formatAUD, toCents, toDollars };
}
