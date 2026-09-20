<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code tries to change or remove a row in an APPEND-ONLY table
 * (audit logs today; the ledger has its own, older exception).
 */
class AppendOnlyException extends RuntimeException
{
    public static function modify(string $what): self
    {
        return new self("{$what} are append-only and can never be modified.");
    }

    public static function remove(string $what): self
    {
        return new self("{$what} are append-only and can never be deleted.");
    }
}
