<?php

namespace App\Modules\CRM\Actions;

use App\Actions\BaseAction;
use App\Exceptions\LeadNotConvertibleException;
use App\Modules\CRM\Events\LeadConverted;
use App\Modules\CRM\Models\Lead;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\DTOs\CreateCustomerDTO;
use App\Modules\Customer\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * One-click lead → customer conversion.
 *
 * Reuses CreateCustomerAction so there is exactly ONE customer-creation path:
 * plan-limit enforcement (max_customers hard block) and the CustomerCreated
 * event come for free, and no customer-creation logic is duplicated here.
 *
 * Wrapped in a transaction: if customer creation hits a plan limit (or anything
 * else throws), the lead is NOT marked converted — the whole thing rolls back.
 */
class ConvertLeadAction extends BaseAction
{
    public function __construct(
        private readonly CreateCustomerAction $createCustomer,
    ) {}

    public function execute(Lead $lead): Customer
    {
        if (! $lead->isConvertible()) {
            throw new LeadNotConvertibleException();
        }

        return DB::transaction(function () use ($lead): Customer {
            $customer = $this->createCustomer->execute($this->toCustomerDTO($lead));

            $lead->update([
                'status' => Lead::STATUS_CONVERTED,
                'converted_at' => now(),
                'converted_customer_id' => $customer->id,
            ]);

            LeadConverted::dispatch($lead, $customer);

            return $customer;
        });
    }

    /**
     * Map lead intake data onto a CreateCustomerDTO.
     *
     * The customers table declares licence_number, emergency_contact_name and
     * emergency_contact_phone as NOT NULL, but a Lead allows them to be null (the
     * customer may not have supplied them on the intake form). We coerce those
     * nulls to empty string on conversion so the insert satisfies the NOT NULL
     * constraint. Customer-facing UI should treat an EMPTY STRING in these fields
     * as "not provided", not as a real value — this is why blank (encrypted)
     * licence numbers can legitimately appear on lead-converted customers.
     *
     * lead.notes carries over to customer.risk_notes, and lead.address to
     * customer.address, so intake context is not lost. rental_start_date /
     * rental_duration have no Customer column and belong to the future Agreement;
     * they intentionally stay on the lead only.
     */
    private function toCustomerDTO(Lead $lead): CreateCustomerDTO
    {
        return new CreateCustomerDTO(
            name: $lead->name,
            email: $lead->email,
            phone: $lead->phone,
            licence_number: $lead->licence_number ?? '',
            emergency_contact_name: $lead->emergency_contact_name ?? '',
            emergency_contact_phone: $lead->emergency_contact_phone ?? '',
            address: $lead->address,
            risk_notes: $lead->notes,
        );
    }
}
