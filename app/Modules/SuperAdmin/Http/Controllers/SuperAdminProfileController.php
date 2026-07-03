<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\ProfileController;
use App\Http\Requests\Profile\UpdateSuperAdminProfileRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Profile settings for super admins (the superadmin guard — platform-wide, no
 * tenant). Email uniqueness is GLOBAL here, unlike the three tenant-scoped
 * guards. Password reset for this guard stays manual/support-only by design;
 * this page is the sanctioned self-service way to change a known password.
 */
class SuperAdminProfileController extends ProfileController
{
    protected function guard(): string
    {
        return 'superadmin';
    }

    protected function profileView(): string
    {
        return 'Profile/SuperAdminProfile';
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

    public function updateProfile(UpdateSuperAdminProfileRequest $request): RedirectResponse
    {
        return $this->applyProfileUpdate($request->safe()->only(['name', 'email']));
    }
}
