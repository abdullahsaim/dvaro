<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\ProfileController;
use App\Http\Requests\Profile\UpdateMechanicProfileRequest;
use App\Http\Requests\Profile\UpdatePinRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Profile settings for the mechanic portal (the mechanic guard). On top of the
 * shared name/email/password actions, mechanics can change their login PIN —
 * identity is verified first (current password OR current PIN, since either
 * may be the mechanic's only credential; see ProfileFormRequest).
 */
class MechanicProfileController extends ProfileController
{
    protected function guard(): string
    {
        return 'mechanic';
    }

    protected function profileView(): string
    {
        return 'Profile/MechanicProfile';
    }

    protected function profileProps(): array
    {
        $user = $this->profileUser();

        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            // Which credentials exist — the page labels the verification field
            // accordingly ("current password or PIN"). Never the values.
            'hasPassword' => $user->password !== null,
            'hasPin' => $user->pin !== null,
        ];
    }

    public function updateProfile(UpdateMechanicProfileRequest $request): RedirectResponse
    {
        return $this->applyProfileUpdate($request->safe()->only(['name', 'email']));
    }

    /**
     * Change the login PIN. The new PIN is hashed by the model's 'hashed' cast.
     */
    public function updatePin(UpdatePinRequest $request): RedirectResponse
    {
        $this->authorizeProfile();

        $this->profileUser()->forceFill([
            'pin' => $request->validated()['pin'],
        ])->save();

        return back()->with('success', __('common.profile.pin_updated'));
    }
}
