<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use App\Modules\Workshop\Models\ServiceLog;
use App\Modules\Workshop\Models\ServiceLogDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Attaches a photo/document to a workshop job. The ONLY sanctioned writer of
 * ServiceLogDocument rows — mirrors UploadCustomerDocumentAction's pattern
 * exactly: content-sniffed extension (not the client-supplied name), hard
 * plan file-size limit, SENSITIVE storage on the default (private) disk only,
 * served solely via FileUrlService's signed URL.
 */
class UploadServiceLogDocumentAction extends BaseAction
{
    public const MAX_KB = 10240; // 10MB absolute ceiling regardless of plan

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
    ) {}

    public function execute(ServiceLog $log, UploadedFile $file, string $uploadedByType, int $uploadedById): ServiceLogDocument
    {
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
        $directory = "tenants/{$log->tenant_id}/workshop/service-logs/{$log->id}";
        $filename = 'doc-'.Str::uuid().'.'.$extension;

        $path = $disk->putFileAs($directory, $file, $filename);

        if ($path === false) {
            throw new \RuntimeException("Failed to store a document for service log {$log->id}.");
        }

        return ServiceLogDocument::create([
            'service_log_id' => $log->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by_type' => $uploadedByType,
            'uploaded_by_id' => $uploadedById,
        ]);
    }

    public function delete(ServiceLogDocument $document): void
    {
        Storage::disk(config('filesystems.default'))->delete($document->path);
        $document->delete();
    }
}
