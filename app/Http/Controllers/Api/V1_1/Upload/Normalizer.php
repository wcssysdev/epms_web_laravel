<?php

namespace App\Http\Controllers\Api\V1_1\Upload;

use Carbon\Carbon;

/**
 * Shared upload normalization helpers, replicating CI3 In.php conventions:
 *   - literal "null" and "" become null
 *   - numeric fields default to 0
 *   - dates: "dd/mm/yyyy" or "yyyy/mm/dd" -> "Y-m-d"
 */
final class Normalizer
{
    /** null when value is null, "", or the literal string "null"; else the value. */
    public static function nn(mixed $v): mixed
    {
        if ($v === null) return null;
        if (is_string($v) && ($v === '' || strtolower($v) === 'null')) return null;
        return $v;
    }

    public static function str(array $row, string $key): ?string
    {
        $v = self::nn($row[$key] ?? null);
        return $v === null ? null : (string) $v;
    }

    public static function intOr0(array $row, string $key): int
    {
        $v = self::nn($row[$key] ?? null);
        return $v === null ? 0 : (int) $v;
    }

    public static function floatOr0(array $row, string $key): float
    {
        $v = self::nn($row[$key] ?? null);
        return $v === null ? 0.0 : (float) $v;
    }

    /** Convert a mobile date string to Y-m-d, or null. */
    public static function date(array $row, string $key): ?string
    {
        $v = self::nn($row[$key] ?? null);
        if ($v === null) return null;
        $s = str_replace('/', '-', (string) $v);
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Combine a mobile date + time into a Y-m-d H:i:s timestamp (today if date missing). */
    public static function timestamp(array $row, string $dateKey, string $timeKey): string
    {
        $d = self::date($row, $dateKey) ?? Carbon::today()->toDateString();
        $t = self::nn($row[$timeKey] ?? null) ?: '00:00:00';
        try {
            return Carbon::parse($d . ' ' . $t)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return Carbon::now()->format('Y-m-d H:i:s');
        }
    }
}
