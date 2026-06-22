<?php

namespace App\Modules\Customer\Actions;

use App\Actions\BaseAction;
use App\Modules\Customer\DTOs\CreateCustomerDTO;
use App\Modules\Customer\Events\CustomerCreated;
use App\Modules\Customer\Models\Customer;
use App\Modules\SaasCore\Services\PlanEnforcementService;

/**
 * Adds a customer to the bound tenant.
 *
 * Plan limits are a HARD BLOCK: the max_customers limit is checked BEFORE the
 * insert. If the tenant is already at its limit, PlanEnforcementService throws
 * PlanLimitExceededException (403 + upgrade message) and nothing is created.
 * (An absent max_customers key resolves to -1 = unlimited, so the check is a
 * no-op until a plan defines it.)
 */
class CreateCustomerAction extends BaseAction
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
    ) {}

    public function execute(CreateCustomerDTO $dto): Customer
    {
        // Customer::count() is tenant-scoped via TenantScope, so this counts
        // only the current tenant's customers. Soft-deleted rows are excluded,
        // which is correct — an archived customer does not occupy a plan slot.
        $this->planEnforcement->check('max_customers', Customer::count());

        $customer = Customer::create($dto->toAttributes());

        CustomerCreated::dispatch($customer);

        return $customer;
    }
}
