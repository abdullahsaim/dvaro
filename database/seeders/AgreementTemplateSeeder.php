<?php

namespace Database\Seeders;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Models\AgreementTemplate;
use Illuminate\Database\Seeder;

/**
 * PLATFORM DEFAULT agreement terms (tenant_id NULL) — one per agreement type.
 *
 * ⚠ THE WORDING BELOW IS SAMPLE TEXT, NOT LEGAL ADVICE. It exists so the
 * feature is usable out of the box and shows how merge fields work. The client
 * (or their lawyer) must replace it via Super Admin → Agreement templates
 * before any real agreement is signed. Every sample opens with a banner saying
 * exactly that, so it can never be mistaken for reviewed terms.
 *
 * IDEMPOTENT (firstOrCreate by type). Not wired into DatabaseSeeder — run on
 * demand:  php artisan db:seed --class=AgreementTemplateSeeder
 */
class AgreementTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $type => [$name, $body]) {
            AgreementTemplate::platformDefaults()->firstOrCreate(
                ['agreement_type' => $type],
                [
                    'tenant_id' => null,
                    'name' => $name,
                    'state' => null,
                    'body_html' => $body,
                    'revision' => 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function templates(): array
    {
        return [
            Agreement::TYPE_PRIVATE => ['Private rental — sample terms', $this->body(
                'private hire',
                '<li>The vehicle may be driven only by {{customer.name}} or a driver named in writing by the company.</li>
                 <li>The vehicle must not be used for hire or reward, including rideshare or food delivery.</li>',
            )],
            Agreement::TYPE_DELIVERY => ['Delivery rental — sample terms', $this->body(
                'delivery work',
                '<li>The vehicle may be used for parcel and food delivery work during the hire period.</li>
                 <li>The hirer must hold the insurance and permits their delivery platform requires.</li>',
            )],
            Agreement::TYPE_RIDESHARE => ['Rideshare rental — sample terms', $this->body(
                'rideshare driving',
                '<li>The vehicle may be used for rideshare driving on an approved platform.</li>
                 <li>The hirer must hold a current passenger transport authorisation for {{agreement.state}}.</li>',
            )],
        ];
    }

    private function body(string $useCase, string $useClauses): string
    {
        return <<<HTML
<p><strong>SAMPLE TERMS — NOT LEGAL ADVICE.</strong> Replace this wording with terms reviewed by your lawyer before using it for a real rental.</p>
<h2>1. Parties and vehicle</h2>
<p>This agreement is made on {{today}} between {{company.name}} ("the company") and {{customer.name}} of {{customer.address}} ("the hirer"), licence number {{customer.licence_number}}, for the hire of {{vehicle.make_model}} ({{vehicle.year}}), registration {{vehicle.registration}}.</p>
<h2>2. Hire period and charges</h2>
<p>The hire starts on {{agreement.start_date}} and ends on {{agreement.end_date}}. The hire charge is {{agreement.rate}}, billed {{agreement.billing_cycle}}. A bond of {{agreement.bond}} is payable before the vehicle is collected and is refundable after the vehicle is returned, less any amounts owing under this agreement.</p>
<h2>3. Use of the vehicle ({$useCase})</h2>
<ul>
{$useClauses}
<li>The hirer must not drive under the influence of alcohol or drugs, or allow anyone else to.</li>
<li>The vehicle must not be taken outside {{agreement.state}} without the company's written consent.</li>
</ul>
<h2>4. Condition, servicing and fuel</h2>
<p>The hirer must keep the vehicle clean, check oil and tyre pressures, present it for scheduled servicing when asked, and return it with the same fuel level it was supplied with.</p>
<h2>5. Damage, accidents and insurance</h2>
<p>The hirer must report any accident, theft or damage to the company within 24 hours and must not admit liability on the company's behalf. The hirer is responsible for the insurance excess for each incident, except where the company agrees otherwise in writing.</p>
<h2>6. Fines and tolls</h2>
<p>The hirer is responsible for all fines, infringements and tolls incurred during the hire period, plus any administration fee the company charges for processing them.</p>
<h2>7. Ending the agreement</h2>
<p>Either party may end this agreement by giving notice in writing. The company may repossess the vehicle immediately if payments fall overdue, if the vehicle is used in breach of this agreement, or if it is not kept safe.</p>
<h2>8. Acknowledgement</h2>
<p>The hirer confirms they have read and understood this agreement, hold a current driver licence, and agree to be bound by these terms. Agreement {{agreement.number}}, governed by the laws of {{agreement.state}}, Australia.</p>
HTML;
    }
}
