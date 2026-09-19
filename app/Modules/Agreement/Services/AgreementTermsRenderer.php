<?php

namespace App\Modules\Agreement\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Services\BaseService;
use Carbon\Carbon;

/**
 * Resolves {{merge.fields}} in agreement terms.
 *
 * SAFETY: every substituted value is HTML-ESCAPED before it goes into the
 * template body, so customer- or vehicle-supplied text can never inject markup
 * into an agreement. The body itself is sanitised separately on save
 * (AgreementTermsSanitizer).
 *
 * Rendering happens ONCE, when an agreement is created — the result is frozen
 * onto agreements.terms_html. Templates are never rendered live for a signed
 * agreement.
 */
class AgreementTermsRenderer extends BaseService
{
    /**
     * Every supported merge field => a short description (drives the editor's
     * "insert field" picker and the save-time validation).
     *
     * @return array<string, string>
     */
    public static function fields(): array
    {
        return [
            'customer.name' => 'Customer full name',
            'customer.email' => 'Customer email',
            'customer.phone' => 'Customer phone',
            'customer.address' => 'Customer address',
            'customer.licence_number' => 'Customer licence number',
            'vehicle.registration' => 'Vehicle registration',
            'vehicle.make_model' => 'Vehicle make and model',
            'vehicle.year' => 'Vehicle year',
            'agreement.number' => 'Agreement number',
            'agreement.type' => 'Agreement type',
            'agreement.state' => 'State',
            'agreement.start_date' => 'Start date',
            'agreement.end_date' => 'End date',
            'agreement.rate' => 'Rate',
            'agreement.billing_cycle' => 'Billing cycle',
            'agreement.bond' => 'Bond / security deposit',
            'company.name' => 'Your company name',
            'today' => "Today's date",
        ];
    }

    /**
     * Merge fields used in $html, in order of appearance.
     *
     * @return list<string>
     */
    public function used(string $html): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', $html, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Merge fields that are NOT supported (blocks the save).
     *
     * @return list<string>
     */
    public function unknown(string $html): array
    {
        $known = array_keys(self::fields());

        return array_values(array_filter($this->used($html), fn (string $f) => ! in_array($f, $known, true)));
    }

    /**
     * Fill every merge field. Unknown or missing values render as an em dash so
     * a gap is visible in the document rather than a raw {{placeholder}}.
     *
     * @param  array<string, string|null>  $values
     */
    public function render(string $html, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            function (array $m) use ($values): string {
                $value = $values[$m[1]] ?? null;

                return $value === null || $value === '' ? '—' : e($value);
            },
            $html,
        );
    }

    /**
     * Values for a real agreement. The agreement must already carry its
     * customer / vehicle relations (or they are lazily loaded).
     *
     * @return array<string, string|null>
     */
    public function valuesFor(Agreement $agreement): array
    {
        $customer = $agreement->customer;
        $vehicle = $agreement->vehicle;
        $tenant = $agreement->tenant ?? app('current_tenant');

        return [
            'customer.name' => $customer?->name,
            'customer.email' => $customer?->email,
            'customer.phone' => $customer?->phone,
            'customer.address' => $customer?->address,
            'customer.licence_number' => $customer?->licence_number,
            'vehicle.registration' => $vehicle?->registration_number,
            'vehicle.make_model' => $vehicle ? trim("{$vehicle->make} {$vehicle->model}") : null,
            'vehicle.year' => $vehicle?->year !== null ? (string) $vehicle->year : null,
            'agreement.number' => $agreement->id !== null ? '#'.$agreement->id : null,
            'agreement.type' => ucfirst((string) $agreement->type),
            'agreement.state' => $agreement->state,
            'agreement.start_date' => $this->date($agreement->start_date),
            'agreement.end_date' => $this->date($agreement->end_date) ?? 'No fixed term',
            'agreement.rate' => $this->money($agreement->rate).' per '.$agreement->billing_cycle,
            'agreement.billing_cycle' => ucfirst((string) $agreement->billing_cycle),
            'agreement.bond' => $this->money($agreement->bond_amount),
            'company.name' => $tenant?->name,
            'today' => now()->format('d/m/Y'),
        ];
    }

    /**
     * Placeholder values for the template editor's live preview.
     *
     * @return array<string, string>
     */
    public function sampleValues(): array
    {
        return [
            'customer.name' => 'Jordan Blake',
            'customer.email' => 'jordan@example.com',
            'customer.phone' => '0412 345 678',
            'customer.address' => '12 Example St, Canning Vale WA 6155',
            'customer.licence_number' => 'WA1234567',
            'vehicle.registration' => '1ABC 234',
            'vehicle.make_model' => 'Toyota Camry',
            'vehicle.year' => '2023',
            'agreement.number' => '#1042',
            'agreement.type' => 'Private',
            'agreement.state' => 'WA',
            'agreement.start_date' => now()->format('d/m/Y'),
            'agreement.end_date' => now()->addMonths(3)->format('d/m/Y'),
            'agreement.rate' => '$350.00 per weekly',
            'agreement.billing_cycle' => 'Weekly',
            'agreement.bond' => '$500.00',
            'company.name' => app()->bound('current_tenant') ? app('current_tenant')->name : 'Your company',
            'today' => now()->format('d/m/Y'),
        ];
    }

    private function money(?int $cents): string
    {
        return '$'.number_format(((int) $cents) / 100, 2);
    }

    private function date(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->format('d/m/Y');
    }
}
