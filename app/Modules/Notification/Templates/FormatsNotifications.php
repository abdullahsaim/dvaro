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

    /**
     * Wrap a heading + paragraphs into a minimal, email-client-safe HTML body
     * (inline styles only — no external CSS, flex, or grid). Every template
     * renders its email body through here so branding stays consistent.
     *
     * @param  list<string>  $paragraphs  already-escaped/plain text lines
     */
    protected function emailHtml(string $heading, array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $line) {
            $body .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.5;color:#334155;">'
                .e($line).'</p>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:24px;">'
            .'<h1 style="margin:0 0 18px;font-size:20px;color:#0f172a;">'.e($heading).'</h1>'
            .$body
            .'<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0 12px;">'
            .'<p style="margin:0;font-size:12px;color:#94a3b8;">Sent by DVARO on behalf of your rental provider.</p>'
            .'</div>';
    }
}
