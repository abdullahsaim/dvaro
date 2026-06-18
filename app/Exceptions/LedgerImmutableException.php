<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when any code attempts to modify or delete a ledger entry.
 *
 * The ledger is the immutable, append-only system of record for all financial
 * events (CLAUDE.md). Entries may only ever be created — never updated, never
 * deleted. This is enforced in code at three layers (model instance methods,
 * model events, and the query builder), and a breach of that invariant is a
 * programming error, not a user-facing condition — hence a plain RuntimeException
 * with no HTTP render.
 */
class LedgerImmutableException extends RuntimeException
{
    public static function modify(): self
    {
        return new self('Ledger entries are append-only and can never be modified.');
    }

    public static function remove(): self
    {
        return new self('Ledger entries are append-only and can never be deleted.');
    }
}
