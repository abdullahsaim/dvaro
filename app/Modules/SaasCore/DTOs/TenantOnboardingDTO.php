<?php

namespace App\Modules\SaasCore\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated tenant self-registration data into TenantOnboardingService.
 *
 * plan_id is nullable — when omitted, onboarding falls back to the default
 * free/first active plan.
 */
class TenantOnboardingDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?int $plan_id = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            // The registration form collects the tenant name as 'company_name';
            // it maps onto the tenant's `name` here.
            name: $request->string('company_name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            plan_id: $request->filled('plan_id') ? (int) $request->input('plan_id') : null,
        );
    }
}
