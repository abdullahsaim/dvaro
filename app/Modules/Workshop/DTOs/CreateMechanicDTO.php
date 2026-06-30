<?php

namespace App\Modules\Workshop\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated new-mechanic data into CreateMechanicAction.
 *
 * No tenant_id (HasTenant auto-populates it from the bound tenant on create).
 * pin/password are passed raw — the Mechanic model's 'hashed' casts hash them on
 * save. At least one of pin/password is guaranteed by StoreMechanicRequest; null
 * credentials are omitted from toAttributes so the hashed cast never sees null.
 */
class CreateMechanicDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly bool $is_active,
        public readonly ?string $phone = null,
        public readonly ?string $pin = null,
        public readonly ?string $password = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            is_active: $request->boolean('is_active'),
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            pin: $request->filled('pin') ? $request->string('pin')->toString() : null,
            password: $request->filled('password') ? $request->string('password')->toString() : null,
        );
    }

    /**
     * Attributes for Mechanic::create(). Null credentials are omitted so the
     * 'hashed' cast is never handed a null value.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $attrs = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
        ];

        if ($this->pin !== null) {
            $attrs['pin'] = $this->pin;
        }

        if ($this->password !== null) {
            $attrs['password'] = $this->password;
        }

        return $attrs;
    }
}
