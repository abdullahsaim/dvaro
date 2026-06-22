<?php

namespace App\Modules\Notification\Templates;

use Carbon\Carbon;

/**
 * Shared formatting for notification templates — AUD money and Australian dates,
 * the platform conventions (CLAUDE.md: AUD default, d/m/Y reporting format).
 *
 * English only in v1 (i18n for notification BODIES is deferred per CLAUDE.md —
 * the UI string layer exists separately; outbound message copy is plain English
 * here until a later i18n pass).
 */
trait FormatsNotifications
{
    /** Cents → "$1,234.56". */
    protected function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    /** Any date-ish value → "d/m/Y" (Australian), or '' when null. */
    protected function date(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }
}
