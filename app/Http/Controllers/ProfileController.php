<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reusable profile-settings flow for ALL FOUR authenticated guards (tenant,
 * customer, mechanic, superadmin) — the same shape as the abstract
 * PortalPasswordResetController. One concrete subclass per guard supplies the
 * guard name, the Inertia view and the page props; the show + password actions
 * live here so every guard behaves identically.
 *
 * Subclasses define their own updateProfile() with their guard's typed
 * FormRequest (the validated field set differs per guard — e.g. CustomerUser
 * has no name) and delegate to applyProfileUpdate().
 *
 * Every action authorizes via Gate::forUser(auth($guard)->user()) — the
 * load-bearing pattern used by every module ($this->authorize() would resolve
 * the empty default web guard and silently skip the check).
 *
 * A profile action only ever mutates the authenticated user's own row, so no
 * id ever comes from the request — "own record" is structural.
 */
abstract class ProfileController extends Controller
{
    /** The auth guard whose user owns this profile page. */
    abstract protected function guard(): string;

    /** Inertia page for the profile screen. */
    abstract protected function profileView(): string;

    /**
     * Props for the profile page. Never the whole model — a slim, explicit
     * payload per guard (the models hide password/pin, but stay deliberate).
     *
     * @return array<string, mixed>
     */
    abstract protected function profileProps(): array;

    public function showProfile(): Response
    {
        $this->authorizeProfile();

        return Inertia::render($this->profileView(), $this->profileProps());
    }

    /**
     * Change the password — shared by all four guards. The current password
     * (or, for mechanics, PIN) was already verified by the request via
     * Hash::check; the new value is hashed by the model's 'hashed' cast.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->authorizeProfile();

        $this->profileUser()->forceFill([
            'password' => $request->validated()['password'],
        ])->save();

        return back()->with('success', __('common.profile.password_updated'));
    }

    protected function profileUser(): Authenticatable
    {
        return auth($this->guard())->user();
    }

    protected function authorizeProfile(): void
    {
        Gate::forUser($this->profileUser())->authorize('manageOwnProfile');
    }

    /**
     * Persist a validated profile update on the authed user's own row.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyProfileUpdate(array $attributes): RedirectResponse
    {
        $this->authorizeProfile();

        $this->profileUser()->forceFill($attributes)->save();

        return back()->with('success', __('common.profile.updated'));
    }
}
