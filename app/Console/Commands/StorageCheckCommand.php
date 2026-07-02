<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deployment health check for the storage layer.
 *
 * Reports the active default (sensitive) disk driver, verifies that disk is
 * writable/readable/deletable with a throwaway probe file, and — when the
 * active driver is local — confirms the public/storage symlink exists (so QR
 * codes + CMS images resolve at /storage/). Run as part of the deploy
 * checklist (docs/MODULE_STATUS.md). Returns a non-zero exit code on any
 * failure so CI/deploy scripts can catch a misconfigured storage layer.
 */
class StorageCheckCommand extends Command
{
    protected $signature = 'storage:check';

    protected $description = 'Verify the configured storage disk is usable and (for local) that the public symlink exists.';

    public function handle(): int
    {
        $default = config('filesystems.default');
        $driver = config("filesystems.disks.{$default}.driver");

        $this->info("Default (sensitive) disk: '{$default}' (driver: {$driver})");
        $this->line("Public assets disk: 'public' (driver: ".config('filesystems.disks.public.driver').')');

        $ok = true;

        // 1) Writable / readable / deletable probe on the default disk.
        if (! $this->probeDisk($default)) {
            $ok = false;
        }

        // 2) Public disk probe (QR codes + CMS images live here).
        if (! $this->probeDisk('public')) {
            $ok = false;
        }

        // 3) For local drivers, the public/storage symlink must exist.
        if ($driver === 'local' || config('filesystems.disks.public.driver') === 'local') {
            if (! $this->checkPublicSymlink()) {
                $ok = false;
            }
        }

        if (! $ok) {
            $this->error('Storage check FAILED — see errors above.');

            return self::FAILURE;
        }

        $this->info('Storage check passed.');

        return self::SUCCESS;
    }

    /**
     * Round-trip a probe file (write → read → delete) on the given disk.
     */
    private function probeDisk(string $disk): bool
    {
        $path = 'storage-check/'.uniqid('probe_', true).'.txt';
        $marker = 'dvaro-storage-check';

        try {
            Storage::disk($disk)->put($path, $marker);

            if (Storage::disk($disk)->get($path) !== $marker) {
                $this->error("[{$disk}] read-back mismatch — disk is not reliably readable.");

                return false;
            }

            Storage::disk($disk)->delete($path);
            $this->line("  [OK] '{$disk}' disk is writable, readable and deletable.");

            return true;
        } catch (\Throwable $e) {
            $this->error("  [FAIL] '{$disk}' disk is not usable: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Confirm public/storage → storage/app/public exists (run storage:link).
     */
    private function checkPublicSymlink(): bool
    {
        $link = public_path('storage');

        if (file_exists($link) || is_link($link)) {
            $this->line('  [OK] public/storage symlink is present.');

            return true;
        }

        $this->error('  [FAIL] public/storage symlink is missing — run: php artisan storage:link');

        return false;
    }
}
