<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Whether a stored PDF (invoice, agreement) is actually there to serve.
 *
 * `pdf_path` being set is not the same thing as the file existing: a database
 * row can point at a path whose file was never copied to a new server, was
 * deleted from disk, or belongs to a disk that has since been reconfigured
 * (FILESYSTEM_DISK changed). Streaming a missing file with Storage::download()
 * throws an UNCAUGHT exception — a raw 500, not a clean 404 — so every place
 * that offers a PDF for download must check existence through here first,
 * never trust the column alone.
 *
 * Shared by InvoiceController, AgreementController and the
 * pdfs:generate-missing recovery command, so the definition of "missing"
 * can never drift between them.
 */
class PdfAvailability
{
    public function exists(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return Storage::disk(config('filesystems.default'))->exists($path);
    }
}
