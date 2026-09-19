<?php

namespace App\Modules\Workshop\Services;

use App\Modules\Fleet\Models\Vehicle;
use App\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * Resolves vehicles for the mechanic portal — by number plate (manual search)
 * or by QR token (scan). Every query runs through TenantScope, so a mechanic
 * can only ever find vehicles in their own tenant's fleet.
 */
class VehicleLookupService extends BaseService
{
    public const MIN_QUERY_LENGTH = 2;

    public const MAX_RESULTS = 10;

    /**
     * Uppercase alphanumerics only: "1abc-234", "1ABC 234" → "1ABC234".
     */
    public static function normalizePlate(string $plate): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    /**
     * Plate search ignoring spaces, dashes, dots and case. Partial matches are
     * allowed (a mechanic may only read part of a plate); exact matches sort
     * first. Returns an empty collection for too-short queries.
     *
     * PostgreSQL: normalisation mirrored in SQL via REGEXP_REPLACE + UPPER.
     *
     * @return Collection<int, Vehicle>
     */
    public function searchByPlate(string $plate): Collection
    {
        $needle = self::normalizePlate($plate);

        if (strlen($needle) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $normalized = "UPPER(REGEXP_REPLACE(registration_number, '[^A-Za-z0-9]', '', 'g'))";

        return Vehicle::query()
            ->select(['id', 'registration_number', 'make', 'model', 'year', 'status'])
            ->whereRaw("{$normalized} LIKE ?", ['%'.$needle.'%'])
            ->orderByRaw("CASE WHEN {$normalized} = ? THEN 0 ELSE 1 END", [$needle])
            ->orderBy('registration_number')
            ->limit(self::MAX_RESULTS)
            ->get();
    }

    /**
     * The vehicle a new service log targets: by id (vehicle page) or, for pages
     * opened before the id-based route existed, by QR token. 404 when not in
     * this tenant's fleet.
     */
    public function resolveForLog(?int $vehicleId, ?string $token): Vehicle
    {
        if ($vehicleId !== null) {
            return Vehicle::findOrFail($vehicleId);
        }

        return $this->findByToken((string) $token);
    }

    public function findByToken(string $token): Vehicle
    {
        return Vehicle::query()
            ->where('qr_code_token', $token)
            ->firstOrFail();
    }
}
