<?php

namespace App\Modules\CRM\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated new-lead data into CreateLeadAction.
 *
 * No tenant_id (HasTenant auto-fills it). No token / created_by — those are
 * generated inside the action (the token must be unguessable, the creator comes
 * from the authenticated tenant user, neither is user-supplied).
 */
class CreateLeadDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $address = null,
        public readonly ?string $licence_number = null,
        public readonly ?string $emergency_contact_name = null,
        public readonly ?string $emergency_contact_phone = null,
        public readonly ?string $rental_start_date = null,
        public readonly ?string $rental_duration = null,
        public readonly ?string $notes = null,
        public readonly ?string $token_expires_at = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            phone: $request->string('phone')->toString(),
            address: $request->filled('address')
                ? $request->string('address')->toString() : null,
            licence_number: $request->filled('licence_number')
                ? $request->string('licence_number')->toString() : null,
            emergency_contact_name: $request->filled('emergency_contact_name')
                ? $request->string('emergency_contact_name')->toString() : null,
            emergency_contact_phone: $request->filled('emergency_contact_phone')
                ? $request->string('emergency_contact_phone')->toString() : null,
            rental_start_date: $request->filled('rental_start_date')
                ? $request->string('rental_start_date')->toString() : null,
            rental_duration: $request->filled('rental_duration')
                ? $request->string('rental_duration')->toString() : null,
            notes: $request->filled('notes')
                ? $request->string('notes')->toString() : null,
            token_expires_at: $request->filled('token_expires_at')
                ? $request->string('token_expires_at')->toString() : null,
        );
    }

    /**
     * Attributes for Lead::create(). token / created_by / status are set by the
     * action, not here.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'licence_number' => $this->licence_number,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'rental_start_date' => $this->rental_start_date,
            'rental_duration' => $this->rental_duration,
            'notes' => $this->notes,
            'token_expires_at' => $this->token_expires_at,
        ];
    }
}
