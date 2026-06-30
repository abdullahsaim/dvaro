<?php

/*
|--------------------------------------------------------------------------
| Password Reset Language Lines (English)
|--------------------------------------------------------------------------
|
| The lines below match Laravel's password broker status constants. The
| 'sent_generic' line is DVARO-specific: it is flashed regardless of whether
| an address was found, so the forgot-password form never reveals whether an
| account exists (user-enumeration defence).
|
*/

return [
    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",

    'sent_generic' => 'If that email matches an account, a password reset link has been sent.',
];
