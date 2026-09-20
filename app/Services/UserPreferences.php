<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-PERSON settings, as opposed to per-company ones (TenantSettingsService).
 *
 * Two people in the same rental company can want different things: the owner
 * lands on the dashboard, the person on the counter wants Rentals; one wants 15
 * rows per page on a laptop, another 100 on a big screen; a mechanic does not
 * want the fleet digest in their inbox every morning. None of that is anyone
 * else's business, so it lives on the user's own row, not in tenant settings.
 *
 * Stored as JSON on each guard's user table, with only the KNOWN keys kept —
 * an old or hand-edited value can never widen what a preference controls.
 * Dark/light mode is deliberately NOT here: it has its own column and its own
 * optimistic endpoint (UserPreferenceController).
 */
class UserPreferences
{
    public const ROWS_PER_PAGE = [15, 25, 50, 100];

    /**
     * Where each guard may land after signing in. The first entry is that
     * guard's default. Each value resolves to "{guard}.dashboard" or
     * "{guard}.{value}.index" — see routeName().
     *
     * Mechanics have a single screen, so they get no choice (the card hides
     * itself rather than offering one option).
     */
    public const LANDING_PAGES = [
        'tenant' => ['dashboard', 'fleet', 'agreements', 'invoices', 'customers', 'leads'],
        'customer' => ['dashboard', 'invoices', 'agreements'],
        'mechanic' => ['dashboard'],
        'superadmin' => ['dashboard', 'tenants', 'subscriptions'],
    ];

    /** Every preference => its default. */
    public const DEFAULTS = [
        'landing_page' => null,      // null = that guard's first landing page
        'rows_per_page' => 15,
        'mute_fleet_digest' => false, // tenant staff only
    ];

    /**
     * Every preference for a user, defaults filled in.
     *
     * @return array<string, mixed>
     */
    public function all(?Model $user): array
    {
        $stored = $user?->preferences;

        return [...self::DEFAULTS, ...(is_array($stored) ? $stored : [])];
    }

    public function get(?Model $user, string $key): mixed
    {
        return $this->all($user)[$key] ?? null;
    }

    /**
     * Save the given preferences, keeping any key not being changed.
     *
     * @param  array<string, mixed>  $changes
     */
    public function save(Model $user, array $changes, string $guard): void
    {
        $user->forceFill([
            'preferences' => [...$this->all($user), ...$this->sanitize($changes, $guard)],
        ])->save();
    }

    /**
     * Keep only known keys with valid values — anything else is dropped rather
     * than stored and trusted later.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitize(array $input, string $guard): array
    {
        $clean = [];

        if (array_key_exists('landing_page', $input)) {
            $page = $input['landing_page'];
            $clean['landing_page'] = in_array($page, self::LANDING_PAGES[$guard] ?? [], true) ? $page : null;
        }

        if (array_key_exists('rows_per_page', $input)) {
            $rows = (int) $input['rows_per_page'];
            $clean['rows_per_page'] = in_array($rows, self::ROWS_PER_PAGE, true) ? $rows : self::DEFAULTS['rows_per_page'];
        }

        // A personal opt-out only means anything for tenant staff, who are the
        // only people the fleet digest is sent to.
        if ($guard === 'tenant' && array_key_exists('mute_fleet_digest', $input)) {
            $clean['mute_fleet_digest'] = filter_var($input['mute_fleet_digest'], FILTER_VALIDATE_BOOLEAN);
        }

        return $clean;
    }

    /**
     * How many rows this person wants per page, for any list. Falls back to the
     * caller's own default when the user has never chosen (so a screen that
     * deliberately shows 30 keeps showing 30).
     */
    public function rowsPerPage(?Model $user, int $fallback = 15): int
    {
        $stored = is_array($user?->preferences) ? ($user->preferences['rows_per_page'] ?? null) : null;

        return in_array($stored, self::ROWS_PER_PAGE, true) ? (int) $stored : $fallback;
    }

    /** The landing page this person chose, or the guard's default. */
    public function landingPage(?Model $user, string $guard): string
    {
        $pages = self::LANDING_PAGES[$guard] ?? ['dashboard'];
        $chosen = $this->get($user, 'landing_page');

        return in_array($chosen, $pages, true) ? $chosen : $pages[0];
    }

    /**
     * The route to send this person to after they sign in. Falls back to the
     * guard's dashboard if the chosen page's route has since disappeared, so a
     * stale preference can never break signing in.
     */
    public function landingRoute(?Model $user, string $guard): string
    {
        $page = $this->landingPage($user, $guard);
        $name = $page === 'dashboard' ? "{$guard}.dashboard" : "{$guard}.{$page}.index";

        return app('router')->has($name) ? $name : "{$guard}.dashboard";
    }
}
