<?php

namespace App\Modules\Finance\Models;

use App\Exceptions\LedgerImmutableException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Custom query builder for LedgerEntry that blocks the mass update/delete paths.
 *
 * Model-level overrides and the `updating`/`deleting` model events do NOT fire
 * for query-builder bulk operations such as:
 *
 *     LedgerEntry::where('id', 1)->update(['amount' => 999]);
 *     LedgerEntry::where('id', 1)->delete();
 *
 * Without this builder those calls would silently bypass immutability. Here we
 * throw before any SQL is issued, so the append-only invariant holds no matter
 * which path the caller takes. Inserts (LedgerEntry::create / append) never
 * route through update()/delete(), so creation is unaffected.
 */
class LedgerEntryBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw LedgerImmutableException::modify();
    }

    public function delete(): mixed
    {
        throw LedgerImmutableException::remove();
    }
}
