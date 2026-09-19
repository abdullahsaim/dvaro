<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Expense;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use App\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Expense receipts — SENSITIVE files, same model as customer documents:
 * the DEFAULT disk (local storage/app/private or private S3), never public;
 * served only via an auth-checked action → FileUrlService signed URL.
 * Path: tenants/{tenant}/expenses/{expense}/receipt-{uuid}.{ext}
 */
class ExpenseReceiptStorage extends BaseService
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    public const MAX_KB = 10240;

    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
    ) {}

    /** Hard plan limit (max_file_size_mb) — call BEFORE any DB write. */
    public function assertAllowed(UploadedFile $file): void
    {
        $this->planEnforcement->checkFileSize((int) $file->getSize());
    }

    public function store(Expense $expense, UploadedFile $file): string
    {
        $extension = strtolower((string) $file->guessExtension());
        $extension = in_array($extension, self::EXTENSIONS, true) ? $extension : 'bin';

        $path = Storage::disk(config('filesystems.default'))->putFileAs(
            "tenants/{$expense->tenant_id}/expenses/{$expense->id}",
            $file,
            'receipt-'.Str::uuid().'.'.$extension,
        );

        if ($path === false) {
            throw new RuntimeException("Failed to store receipt for expense {$expense->id}.");
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(config('filesystems.default'))->delete($path);
        }
    }
}
