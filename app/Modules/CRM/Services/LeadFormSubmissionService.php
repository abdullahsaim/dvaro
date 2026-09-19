<?php

namespace App\Modules\CRM\Services;

use App\Contracts\CaptchaVerifierInterface;
use App\Modules\CRM\Actions\CaptureLeadAction;
use App\Modules\CRM\Models\Lead;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;

/**
 * Orchestrates one PUBLIC lead-form submission, in order:
 *   1. field validation          → invalid (errors shown on the form)
 *   2. honeypot filled           → spam (bot sees a normal "thanks", nothing saved)
 *   3. too fast / forged timing  → invalid (form-level "please try again")
 *   4. reCAPTCHA                 → failed = invalid; unavailable = accepted but
 *                                  flagged captcha_status "unverified"
 *   5. CaptureLeadAction         → created
 *
 * The tenant must already be bound (Lead uses HasTenant). No session is used —
 * the embed runs in a cross-site iframe where DVARO's cookies aren't sent.
 */
class LeadFormSubmissionService extends BaseService
{
    public const HONEYPOT_FIELD = 'company_website';

    public const RESULT_CREATED = 'created';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_SPAM = 'spam';

    public function __construct(
        private readonly LeadFormService $forms,
        private readonly CaptchaVerifierInterface $captcha,
        private readonly CaptureLeadAction $capture,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'rental_start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'rental_duration' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: string, errors: array<string, string>, lead: ?Lead}
     */
    public function handle(
        Tenant $tenant,
        array $input,
        ?string $ip,
        bool $embedded,
        ?string $referrerUrl,
    ): array {
        $validator = Validator::make($input, self::rules(), [], [
            'rental_start_date' => __('common.crm.lead_form_start_date_attribute'),
        ]);

        if ($validator->fails()) {
            return $this->result(self::RESULT_INVALID, collect($validator->errors()->messages())
                ->map(fn (array $messages) => $messages[0])
                ->all());
        }

        if (filled($input[self::HONEYPOT_FIELD] ?? null)) {
            return $this->result(self::RESULT_SPAM);
        }

        if (! $this->forms->isHumanPaced($input['started'] ?? null)) {
            return $this->result(self::RESULT_INVALID, ['form' => __('common.crm.lead_form_retry')]);
        }

        $captcha = $this->captcha->verify($input['g-recaptcha-response'] ?? null, $ip);

        if ($captcha === CaptchaVerifierInterface::RESULT_FAILED) {
            return $this->result(self::RESULT_INVALID, ['captcha' => __('common.crm.lead_form_captcha_failed')]);
        }

        $lead = $this->capture->execute(
            $validator->validated(),
            $embedded ? Lead::SOURCE_EMBED : Lead::SOURCE_PUBLIC_FORM,
            $captcha === CaptchaVerifierInterface::RESULT_PASSED ? Lead::CAPTCHA_PASSED : Lead::CAPTCHA_UNVERIFIED,
            $ip,
            $embedded ? $this->safeUrl($referrerUrl) : null,
        );

        return $this->result(self::RESULT_CREATED, [], $lead);
    }

    /**
     * @param  array<string, string>  $errors
     * @return array{status: string, errors: array<string, string>, lead: ?Lead}
     */
    private function result(string $status, array $errors = [], ?Lead $lead = null): array
    {
        return ['status' => $status, 'errors' => $errors, 'lead' => $lead];
    }

    /** Keep only http(s) URLs (the value comes from the host page, untrusted). */
    private function safeUrl(?string $url): ?string
    {
        return $url !== null && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)
            ? $url
            : null;
    }
}
