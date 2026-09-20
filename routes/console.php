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

// Send 14-day agreement-end reminders to tenant admins. (Vehicle reminders
// moved to the fleet digest below.) Tenant-aware, idempotent, never throws.
Schedule::command('notifications:send-expiry-reminders')
    ->dailyAt('00:10')
    ->timezone('Australia/Sydney')
    ->withoutOverlapping();

// Fleet reminder digest to ALL tenant staff — registration, insurance and
// service by date OR km (due soon + overdue, each sent once per due value).
//
// Runs HOURLY and sweeps only the tenants whose OWN timezone reads 07:00, so
// every company gets it at 7am local (Settings → Regional). A single daily
// Sydney run would reach Perth at 4am.
Schedule::command('notifications:send-fleet-reminders --hour=7')
    ->hourly()
    ->withoutOverlapping();
