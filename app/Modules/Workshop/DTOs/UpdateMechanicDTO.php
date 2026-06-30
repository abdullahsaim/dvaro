<?php

namespace App\Modules\Workshop\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated mechanic-edit data into UpdateMechanicAction.
 *
 * pin/password are OPTIONAL on edit: a blank field means "leave the existing
 * credential unchanged", so blank values are omitted from toAttributes (never
 * overwriting a stored hash with null). Non-blank values are passed raw and the
 * model's 'hashed' casts hash them on save.
 */
class UpdateMechanicDTO extends BaseDTO
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
     * Attributes for $mechanic->update(). phone is always written (so it can be
     * cleared); pin/password only when provided (blank keeps the existing hash).
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
