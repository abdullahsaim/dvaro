<?php

namespace App\Modules\Agreement\Services;

use App\Exceptions\AgreementNotSignableException;
use App\Jobs\GenerateAgreementPdfJob;
use App\Modules\Agreement\DTOs\CreateAgreementDTO;
use App\Modules\Agreement\Events\AgreementCreated;
use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\Agreement\Events\AgreementVersionCreated;
use App\Modules\Agreement\Models\Agreement;
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
            $agreement->update([
                'signature_data' => $signatureData,
                'signed_at' => now(),
                'status' => Agreement::STATUS_SIGNED,
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

            AgreementVersionCreated::dispatch($agreement, $newVersion);

            return $newVersion;
        });
    }
}
