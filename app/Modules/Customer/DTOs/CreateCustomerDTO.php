<?php

namespace App\Modules\Customer\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated new-customer data into CreateCustomerAction.
 *
 * No tenant_id (HasTenant auto-populates it from the bound tenant on create).
 * Blacklist fields are deliberately absent — a customer is never created
 * blacklisted; that state is mutated only through BlacklistCustomerAction.
 * licence_number / passport_number are encrypted at the model layer.
 */
class CreateCustomerDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $licence_number,
        public readonly string $emergency_contact_name,
        public readonly string $emergency_contact_phone,
        public readonly ?string $date_of_birth = null,
        public readonly ?string $licence_expiry = null,
        public readonly ?string $passport_number = null,
        public readonly ?string $address = null,
        public readonly ?string $risk_notes = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            phone: $request->string('phone')->toString(),
            licence_number: $request->string('licence_number')->toString(),
            emergency_contact_name: $request->string('emergency_contact_name')->toString(),
            emergency_contact_phone: $request->string('emergency_contact_phone')->toString(),
            date_of_birth: $request->filled('date_of_birth')
                ? $request->string('date_of_birth')->toString() : null,
            licence_expiry: $request->filled('licence_expiry')
                ? $request->string('licence_expiry')->toString() : null,
            passport_number: $request->filled('passport_number')
                ? $request->string('passport_number')->toString() : null,
            address: $request->filled('address')
                ? $request->string('address')->toString() : null,
            risk_notes: $request->filled('risk_notes')
                ? $request->string('risk_notes')->toString() : null,
        );
    }

    /**
     * Attributes for Customer::create(). Keys map 1:1 to fillable columns.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'licence_number' => $this->licence_number,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'date_of_birth' => $this->date_of_birth,
            'licence_expiry' => $this->licence_expiry,
            'passport_number' => $this->passport_number,
            'address' => $this->address,
            'risk_notes' => $this->risk_notes,
        ];
    }
}
