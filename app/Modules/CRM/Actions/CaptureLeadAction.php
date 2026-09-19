<?php

namespace App\Modules\CRM\Actions;

use App\Actions\BaseAction;
use App\Modules\CRM\Events\LeadCreated;
use App\Modules\CRM\Events\LeadSubmitted;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Str;

/**
 * Creates a lead from the tenant's PUBLIC lead form (share link / QR / website
 * embed). The tenant MUST already be bound (HasTenant fills tenant_id).
 *
 * The lead arrives already filled in, so it is stamped submitted and fires
 * BOTH LeadCreated and LeadSubmitted — the latter drives the existing admin
 * "new lead" notification (SendLeadSubmittedNotification). created_by is null:
 * no staff member created it. A server-generated token is still issued so the
 * lead can later be sent the detailed per-lead intake link.
 */
class CaptureLeadAction extends BaseAction
{
    /**
     * @param  array{name:string, email:string, phone:string, rental_start_date?:?string, rental_duration?:?string, notes?:?string}  $data
     */
    public function execute(
        array $data,
        string $source,
        string $captchaStatus,
        ?string $ip = null,
        ?string $referrerUrl = null,
    ): Lead {
        $lead = Lead::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'rental_start_date' => $data['rental_start_date'] ?? null,
            'rental_duration' => $data['rental_duration'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => Lead::STATUS_NEW,
            'token' => (string) Str::uuid(),
            'submitted_at' => now(),
            'source' => $source,
            'captcha_status' => $captchaStatus,
            'submitted_ip' => $ip,
            'referrer_url' => $referrerUrl !== null ? Str::limit($referrerUrl, 2000, '') : null,
        ]);

        LeadCreated::dispatch($lead);
        LeadSubmitted::dispatch($lead);

        return $lead;
    }
}
