<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Billing automation runs just after midnight Australia/Sydney (the platform
| timezone). withoutOverlapping() guards against a long run colliding with the
| next tick. Both commands are tenant-aware and swallow per-tenant failures.
|
*/

// Raise the day's due recurring invoices (all tenants).
Schedule::command('invoices:generate-recurring')
    ->dailyAt('00:01')
    ->timezone('Australia/Sydney')
    ->withoutOverlapping();

// Apply late fees to invoices past their grace period (all tenants).
Schedule::command('invoices:apply-late-fees')
    ->dailyAt('00:05')
    ->timezone('Australia/Sydney')
    ->withoutOverlapping();

// Send 14-day expiry reminders to tenant admins (registration, insurance,
// service due, agreement end). Tenant-aware, idempotent, never throws.
Schedule::command('notifications:send-expiry-reminders')
    ->dailyAt('00:10')
    ->timezone('Australia/Sydney')
    ->withoutOverlapping();
