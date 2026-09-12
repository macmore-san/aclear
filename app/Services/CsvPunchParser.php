<?php

namespace App\Services;

use App\Exceptions\CsvFormatException;
use Carbon\Carbon;

/**
 * Parses the `sTime` column of a ZKTeco punch export.
 *
 * Unlike the stricter DOH-project parser this one never rejects a file over an
 * ambiguous or mixed date format — it decides its best guess and imports, so a
 * small business doesn't have to re-export a file to match a particular shape.
 * Bare Carbon::parse() still can't be used directly: PHP always reads a
 * slash-separated date as American m/d/Y, so a dd/mm/yyyy export would
 * silently land on the wrong month.
 */
final class CsvPunchParser
{
    public const FORMAT_ISO = 'iso';

    public const FORMAT_DMY = 'dmy';

    public const FORMAT_MDY = 'mdy';

    /** Exception codes from parse(), so the caller can bucket skipped rows by reason. */
    public const CODE_NO_TIME = 1;

    public const CODE_UNPARSABLE = 2;

    /** Matches "2026-08-01 08:05" / "2026-08-01T08:05:00". */
    private const RE_ISO = '#^(\d{4})-(\d{1,2})-(\d{1,2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$#';

    /** Matches "01/08/2026 08:05" and "01-08-2026 08:05" — field order still unknown. */
    private const RE_SLASH = '#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$#';

    /** Same shapes with a 12-hour clock and AM/PM suffix, e.g. "01/08/2026 8:05 AM". */
    private const RE_SLASH_AMPM = '#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp][Mm])$#';

    private const RE_ISO_AMPM = '#^(\d{4})-(\d{1,2})-(\d{1,2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp][Mm])$#';

    /** Same shapes with the time of day missing. */
    private const RE_ISO_DATE_ONLY = '#^\d{4}-\d{1,2}-\d{1,2}$#';

    private const RE_SLASH_DATE_ONLY = '#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#';

    public static function label(string $format): string
    {
        return match ($format) {
            self::FORMAT_ISO => 'yyyy-mm-dd',
            self::FORMAT_DMY => 'dd/mm/yyyy',
            self::FORMAT_MDY => 'mm/dd/yyyy',
            default => $format,
        };
    }

    /**
     * Decide the file's date format from every non-empty sTime value it contains.
     *
     * Only throws when NOTHING in the file is recognisable as a date at all.
     * An ambiguous file (every day/month <= 12) or one that mixes dd/mm and
     * mm/dd rows falls back to config('dtr.ambiguous_date_format') rather than
     * being rejected — the caller is told via the second return value so it can
     * flag the file as "format assumed" in the UI.
     *
     * @param  string[]  $samples
     * @return array{0: string, 1: bool} [format, assumed]
     *
     * @throws CsvFormatException when no row in the file is a recognisable date
     */
    public function detectFormat(array $samples): array
    {
        $iso = 0;
        $slash = 0;
        $firstGt12 = false;   // some row has field 1 > 12 -> field 1 is the day
        $secondGt12 = false;   // some row has field 2 > 12 -> field 2 is the day

        foreach ($samples as $value) {
            $stripped = $this->stripAmPmMarker($value);
            $checkValue = $stripped['value'] ?? $value;

            if (preg_match(self::RE_ISO, $checkValue) || preg_match(self::RE_ISO_DATE_ONLY, $checkValue)) {
                $iso++;

                continue;
            }

            if (preg_match(self::RE_SLASH, $checkValue, $m) || preg_match(self::RE_SLASH_DATE_ONLY, $checkValue, $m)) {
                $slash++;
                if ((int) $m[1] > 12) {
                    $firstGt12 = true;
                }
                if ((int) $m[2] > 12) {
                    $secondGt12 = true;
                }
            }
        }

        if ($iso === 0 && $slash === 0) {
            throw new CsvFormatException('No usable values found in the sTime column.');
        }

        if ($slash === 0) {
            return [self::FORMAT_ISO, false];
        }

        if ($iso > 0) {
            // Mixed ISO + slash rows: import both, ISO parses unambiguously either way.
            return [self::FORMAT_ISO, true];
        }

        if ($firstGt12 && $secondGt12) {
            // File mixes dd/mm and mm/dd rows — no single format is correct for all of
            // them, so fall back to the configured default rather than reject the file.
            return [config('dtr.ambiguous_date_format', self::FORMAT_DMY), true];
        }

        if ($firstGt12) {
            return [self::FORMAT_DMY, false];
        }
        if ($secondGt12) {
            return [self::FORMAT_MDY, false];
        }

        // Every day and month is <= 12: genuinely ambiguous, use the configured default.
        return [config('dtr.ambiguous_date_format', self::FORMAT_DMY), true];
    }

    /**
     * Parse one sTime value using the format already detected for the file.
     *
     * @throws CsvFormatException with a machine-readable code: CODE_NO_TIME for a date
     *                            with no time of day, CODE_UNPARSABLE for anything else.
     */
    public function parse(string $sTime, string $format): Carbon
    {
        if (preg_match(self::RE_ISO_DATE_ONLY, $sTime) || preg_match(self::RE_SLASH_DATE_ONLY, $sTime)) {
            throw new CsvFormatException("\"{$sTime}\" has a date but no time of day.", self::CODE_NO_TIME);
        }

        $ampm = $this->stripAmPmMarker($sTime);
        $value = $ampm['value'] ?? $sTime;

        // ISO rows always parse unambiguously regardless of the file's detected format,
        // which matters for a file that mixes ISO and slash-separated rows.
        $re = preg_match(self::RE_ISO, $value) ? self::FORMAT_ISO : $format;
        $pattern = $re === self::FORMAT_ISO ? self::RE_ISO : self::RE_SLASH;

        if (! preg_match($pattern, $value, $m)) {
            throw new CsvFormatException("\"{$sTime}\" does not match the file's date format.", self::CODE_UNPARSABLE);
        }

        if ($re === self::FORMAT_ISO) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif ($re === self::FORMAT_DMY) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            [$month, $day, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        }

        $hour = (int) $m[4];
        $minute = (int) $m[5];
        $second = (int) ($m[6] ?? 0);

        if ($ampm !== null) {
            $hour = $this->to24Hour($hour, $ampm['marker']);
        }

        // checkdate rejects the overflow createFromFormat would silently roll over
        // (13/13/2026 -> Jan 2027), which is how a misdetected format stays invisible.
        if (! checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new CsvFormatException("\"{$sTime}\" is not a valid date and time.", self::CODE_UNPARSABLE);
        }

        return Carbon::create($year, $month, $day, $hour, $minute, $second);
    }

    /**
     * Splits a trailing AM/PM marker off a value so the rest can be matched by the
     * 24-hour regexes. Returns null when there is no marker.
     *
     * @return array{value: string, marker: string}|null
     */
    private function stripAmPmMarker(string $value): ?array
    {
        if (preg_match(self::RE_SLASH_AMPM, $value, $m) || preg_match(self::RE_ISO_AMPM, $value, $m)) {
            $marker = strtoupper($m[7]);
            $value = preg_replace('/\s*[AaPp][Mm]$/', '', $value);

            return ['value' => $value, 'marker' => $marker];
        }

        return null;
    }

    private function to24Hour(int $hour12, string $marker): int
    {
        if ($marker === 'AM') {
            return $hour12 === 12 ? 0 : $hour12;
        }

        return $hour12 === 12 ? 12 : $hour12 + 12;
    }
}
