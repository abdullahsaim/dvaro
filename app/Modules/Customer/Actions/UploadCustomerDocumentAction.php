<?php

namespace App\Modules\Customer\Actions;

use App\Actions\BaseAction;
use App\Modules\Customer\Events\CustomerDocumentUploaded;
use App\Modules\Customer\Models\Customer;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores a customer identity document (licence front/back, proof of address).
 * The ONLY sanctioned writer of the Customer *_path columns.
 *
 * SENSITIVE: files go to the DEFAULT disk (config('filesystems.default') —
 * local storage/app/private or private S3), never the public disk, and are
 * only ever served via an auth-checked action → FileUrlService signed URL.
 *
 * Type / extension / size are re-checked here (not only in the FormRequest) so
 * the rules hold for any caller, e.g. a future /api/v1 controller.
 *
 * Replacement order is store-new → update column → delete-old, so a failed
 * write never leaves the customer without their existing document.
 */
class UploadCustomerDocumentAction extends BaseAction
{
    /** Absolute ceiling regardless of plan (10MB). */
    public const MAX_KB = 10240;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
    ) {}

    public function execute(Customer $customer, string $type, UploadedFile $file): Customer
    {
        $column = Customer::DOCUMENT_TYPES[$type] ?? null;

        if ($column === null) {
            throw ValidationException::withMessages(['type' => __('validation.in', ['attribute' => 'type'])]);
        }

        // Content-sniffed extension (not the client-supplied name).
        $extension = strtolower((string) $file->guessExtension());

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => __('validation.mimes', ['attribute' => 'file', 'values' => implode(', ', self::EXTENSIONS)]),
            ]);
        }

        if ($file->getSize() > self::MAX_KB * 1024) {
            throw ValidationException::withMessages([
                'file' => __('validation.max.file', ['attribute' => 'file', 'max' => self::MAX_KB]),
            ]);
        }

        // Hard block on the tenant's plan limit (PlanLimitExceededException).
        $this->planEnforcement->checkFileSize($file->getSize());

        $disk = Storage::disk(config('filesystems.default'));
        $directory = "tenants/{$customer->tenant_id}/customers/{$customer->id}";
        $filename = $type.'-'.Str::uuid().'.'.$extension;

        $newPath = $disk->putFileAs($directory, $file, $filename);

        if ($newPath === false) {
            throw new \RuntimeException("Failed to store customer document [{$type}] for customer {$customer->id}.");
        }

        $oldPath = $customer->{$column};

        $customer->forceFill([$column => $newPath])->save();

        if ($oldPath !== null && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        CustomerDocumentUploaded::dispatch($customer, $type, $oldPath !== null);

        return $customer;
    }
}
