<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Regional: timezone, currency and date format.
 *
 * TIMEZONE is the one that matters: it drives what staff see, what PDFs and
 * emails say, and WHEN the daily fleet digest goes out (the command runs
 * hourly and fires at 07:00 in each tenant's own zone). Stored timestamps are
 * never rewritten — this is display + scheduling only.
 *
 * Currency is AUD-only for now; the setting exists so formatting reads it
 * rather than hard-coding it.
 */
class RegionalSettingsController extends Controller
{
    public function __construct(
        private readonly TenantSettingsService $settings,
    ) {}

    public function show(): Response
    {
        $all = $this->settings->all(app('current_tenant'));

        return Inertia::render('Settings/Regional', [
            'settings' => [
                'timezone' => $all['timezone'],
                'currency' => $all['currency'],
                'date_format' => $all['date_format'],
            ],
            // Each zone's current local time, so the choice is obvious.
            'timezones' => collect(TenantSettingsService::TIMEZONES)
                ->map(fn (string $tz) => [
                    'value' => $tz,
                    'label' => str_replace(['Australia/', '_'], ['', ' '], $tz),
                    'now' => Carbon::now($tz)->format('g:i a'),
                ])
                ->all(),
            'dateFormats' => collect(TenantSettingsService::DATE_FORMATS)
                ->map(fn (string $format) => [
                    'value' => $format,
                    'example' => Carbon::now($all['timezone'])->format($format),
                ])
                ->all(),
            'canManage' => $this->isAdmin(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);

        $data = $request->validate([
            'timezone' => ['required', Rule::in(TenantSettingsService::TIMEZONES)],
            'currency' => ['required', Rule::in(['AUD'])],
            'date_format' => ['required', Rule::in(TenantSettingsService::DATE_FORMATS)],
        ]);

        $this->settings->update(app('current_tenant'), $data, 'regional');

        return back()->with('success', __('common.settings.regional_saved'));
    }

    private function isAdmin(): bool
    {
        $user = auth('tenant')->user();

        return $user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN;
    }
}
