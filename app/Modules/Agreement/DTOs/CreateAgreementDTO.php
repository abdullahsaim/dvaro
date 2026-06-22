<?php

namespace App\Modules\Agreement\DTOs;

use App\DTOs\BaseDTO;
use App\Modules\Agreement\Models\Agreement;
use Illuminate\Http\Request;

/**
 * Carries validated agreement data into AgreementService::create() and
 * ::createNewVersion().
 *
 * No tenant_id (HasTenant auto-populates it from the bound tenant on create).
 * No status/version/parent_agreement_id — those are decided by the service
 * (create() => draft/v1/no-parent; createNewVersion() => draft/v+1/parent set).
 * Monetary values (rate, bond_amount) are integer cents per CLAUDE.md.
 */
class CreateAgreementDTO extends BaseDTO
{
    public function __construct(
        public readonly int $customer_id,
        public readonly int $vehicle_id,
        public readonly string $type,
        public readonly string $billing_cycle,
        public readonly int $rate,
        public readonly string $start_date,
        public readonly ?string $billing_cycle_day = null,
        public readonly int $bond_amount = 0,
        public readonly ?string $end_date = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            customer_id: (int) $request->integer('customer_id'),
            vehicle_id: (int) $request->integer('vehicle_id'),
            type: $request->string('type')->toString(),
            billing_cycle: $request->string('billing_cycle')->toString(),
            rate: (int) $request->integer('rate'),
            start_date: $request->string('start_date')->toString(),
            billing_cycle_day: $request->filled('billing_cycle_day')
                ? $request->string('billing_cycle_day')->toString() : null,
            bond_amount: (int) $request->integer('bond_amount'),
            end_date: $request->filled('end_date')
                ? $request->string('end_date')->toString() : null,
            notes: $request->filled('notes')
                ? $request->string('notes')->toString() : null,
        );
    }

    /**
     * Rebuild a DTO from an existing agreement — the basis for a new version
     * (the service overrides status/version/parent). Dates are normalised to
     * Y-m-d strings to match the fromRequest() shape.
     */
    public static function fromAgreement(Agreement $agreement): self
    {
        return new self(
            customer_id: (int) $agreement->customer_id,
            vehicle_id: (int) $agreement->vehicle_id,
            type: $agreement->type,
            billing_cycle: $agreement->billing_cycle,
            rate: (int) $agreement->rate,
            start_date: $agreement->start_date->format('Y-m-d'),
            billing_cycle_day: $agreement->billing_cycle_day,
            bond_amount: (int) $agreement->bond_amount,
            end_date: $agreement->end_date?->format('Y-m-d'),
            notes: $agreement->notes,
        );
    }

    /**
     * Attributes shared by every version. Keys map 1:1 to fillable columns.
     * status / version / parent_agreement_id are set by the service, not here.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'customer_id' => $this->customer_id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type,
            'billing_cycle' => $this->billing_cycle,
            'billing_cycle_day' => $this->billing_cycle_day,
            'rate' => $this->rate,
            'bond_amount' => $this->bond_amount,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'notes' => $this->notes,
        ];
    }
}
