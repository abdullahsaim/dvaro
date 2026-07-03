<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\ProfileController;
use App\Http\Requests\Profile\UpdateTenantProfileRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Profile settings for tenant staff (the tenant guard). Name + email + password.
 */
class TenantProfileController extends ProfileController
{
    protected function guard(): string
    {
        return 'tenant';
    }

    protected function profileView(): string
    {
        return 'Profile/TenantProfile';
    }

    protected function profileProps(): array
    {
        $user = $this->profileUser();

        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }

    public function updateProfile(UpdateTenantProfileRequest $request): RedirectResponse
    {
        return $this->applyProfileUpdate($request->safe()->only(['name', 'email']));
    }
}
