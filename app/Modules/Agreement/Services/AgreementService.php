<?php

namespace App\Modules\Agreement\Services;

use App\Exceptions\AgreementNotSignableException;
use App\Jobs\GenerateAgreementPdfJob;
use App\Modules\Agreement\DTOs\CreateAgreementDTO;
use App\Modules\Agreement\Events\AgreementCreated;
use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\Agreement\Events\AgreementVersionCreated;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Models\AgreementTemplate;
use App\Modules\Invoice\Services\NextBillingDateService;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Agreement lifecycle: creation, signing, and immutable versioning.
 *
 * Agreements are the source of truth for a rental (CLAUDE.md). They are
 * IMMUTABLE in the sense that a CHANGE never edits a row — it produces a new
 * version. The only in-place mutation permitted is the sanctioned status
 * transition draft → signed performed by sign() (which also records the
 * signature). All three operations are wrapped in a DB transaction.
 *
 * No invoice triggering here — that is a later session. sign() only generates
 * the PDF (queued) and stores it; it does not email anything.
 */
class AgreementService extends BaseService
{
    public function __construct(
        private readonly NextBillingDateService $nextBilling,
        private readonly AgreementTemplateService $templates,
        private readonly AgreementTermsRenderer $renderer,
    ) {}

    /**
     * Create a brand-new agreement: status=draft, version=1, no parent.
     */
    public function create(CreateAgreementDTO $dto): Agreement
    {
        return DB::transaction(function () use ($dto): Agreement {
            // HasTenant fills tenant_id from the bound tenant on create.
            $agreement = Agreement::create([
                ...$dto->toAttributes(),
                'status' => Agreement::STATUS_DRAFT,
                'version' => 1,
                'parent_agreement_id' => null,
            ]);

            // FREEZE the terms: resolve the template, fill in the merge fields
            // and store both the resolved text and the wording it came from.
            // Editing the template later must never change this agreement.
            $this->freezeTerms($agreement, $this->resolveTemplate($agreement, $dto->agreement_template_id));

            AgreementCreated::dispatch($agreement);

            return $agreement;
        });
    }

    /**
     * Sign a draft agreement: store the canvas signature, stamp signed_at, and
     * transition to signed. Then queue PDF generation.
     *
     * Only a draft can be signed — anything else throws (a signed agreement is
     * never re-signed; a change is a new version that must be signed afresh).
     */
    public function sign(Agreement $agreement, string $signatureData): Agreement
    {
        if ($agreement->status !== Agreement::STATUS_DRAFT) {
            throw new AgreementNotSignableException();
        }

        $signed = DB::transaction(function () use ($agreement, $signatureData): Agreement {
            // signature_data stored as-is (base64 canvas data URL). This is the
            // ONE sanctioned in-place mutation — a status transition, not an edit.
            // next_billing_date is seeded here too: it is the date the SECOND
            // recurring invoice falls due (the first covers the start_date period
            // and is raised by RecurringInvoiceService). It is operational billing
            // metadata, not a term, so writing it does not breach immutability.
            $agreement->update([
                'signature_data' => $signatureData,
                'signed_at' => now(),
                'status' => Agreement::STATUS_SIGNED,
                'next_billing_date' => $this->nextBilling
                    ->calculate($agreement, $agreement->start_date)
                    ->toDateString(),
            ]);

            AgreementSigned::dispatch($agreement);

            return $agreement;
        });

        // Dispatch AFTER the transaction commits so the queue worker (which has
        // no bound tenant) reads a persisted row. PDF is ALWAYS queued, never
        // synchronous (CLAUDE.md). Pass ids only — the job re-loads scope-free.
        GenerateAgreementPdfJob::dispatch($signed->id, $signed->tenant_id);

        return $signed;
    }

    /**
     * Create a NEW version of an agreement — never mutates the existing row.
     * The new version points back at its predecessor (parent_agreement_id),
     * increments version, and starts as a draft that must be signed afresh.
     */
    public function createNewVersion(Agreement $agreement, CreateAgreementDTO $dto): Agreement
    {
        return DB::transaction(function () use ($agreement, $dto): Agreement {
            $newVersion = Agreement::create([
                ...$dto->toAttributes(),
                'status' => Agreement::STATUS_DRAFT,
                'version' => $agreement->version + 1,
                'parent_agreement_id' => $agreement->id,
            ]);

            // A new version keeps the WORDING THE CUSTOMER AGREED TO (the
            // parent's terms_source), re-filled with this version's details
            // (e.g. a swapped vehicle). Only if the parent predates templates
            // do we fall back to resolving one.
            if (filled($agreement->terms_source)) {
                $newVersion->forceFill([
                    'agreement_template_id' => $agreement->agreement_template_id,
                    'template_revision' => $agreement->template_revision,
                    'terms_source' => $agreement->terms_source,
                    'terms_html' => $this->renderer->render(
                        $agreement->terms_source,
                        $this->renderer->valuesFor($newVersion),
                    ),
                ])->save();
            } else {
                $this->freezeTerms($newVersion, $this->resolveTemplate($newVersion, $dto->agreement_template_id));
            }

            AgreementVersionCreated::dispatch($agreement, $newVersion);

            return $newVersion;
        });
    }

    /**
     * The template for this agreement: the one explicitly chosen on the form
     * (validated to be visible to the tenant), else the selection cascade.
     */
    private function resolveTemplate(Agreement $agreement, ?int $templateId): ?AgreementTemplate
    {
        if ($templateId !== null) {
            $chosen = AgreementTemplate::visibleTo((int) $agreement->tenant_id)
                ->active()
                ->find($templateId);

            if ($chosen !== null) {
                return $chosen;
            }
        }

        return $this->templates->resolveFor((int) $agreement->tenant_id, $agreement->type, $agreement->state);
    }

    /**
     * Stamp the resolved terms onto the agreement — ONCE, at creation. Both the
     * filled-in text (terms_html) and the wording it came from (terms_source)
     * are stored, so a later version can reuse the exact wording the customer
     * agreed to even after the template is edited. No template → no terms (the
     * agreement still works; the PDF simply has no terms section).
     */
    private function freezeTerms(Agreement $agreement, ?AgreementTemplate $template): void
    {
        if ($template === null) {
            return;
        }

        $agreement->forceFill([
            'agreement_template_id' => $template->id,
            'template_revision' => $template->revision,
            'terms_source' => $template->body_html,
            'terms_html' => $this->renderer->render($template->body_html, $this->renderer->valuesFor($agreement)),
        ])->save();
    }
}
