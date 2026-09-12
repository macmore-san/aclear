<?php

namespace Tests\Unit;

use App\Exceptions\CsvFormatException;
use App\Services\CsvPunchParser;
use Tests\TestCase;

class CsvPunchParserTest extends TestCase
{
    public function test_detects_iso_format(): void
    {
        [$format, $assumed] = (new CsvPunchParser)->detectFormat(['2026-08-01 08:05', '2026-08-15 17:30']);

        $this->assertSame(CsvPunchParser::FORMAT_ISO, $format);
        $this->assertFalse($assumed);
    }

    public function test_detects_dmy_when_first_field_exceeds_12(): void
    {
        [$format, $assumed] = (new CsvPunchParser)->detectFormat(['25/03/2026 08:05']);

        $this->assertSame(CsvPunchParser::FORMAT_DMY, $format);
        $this->assertFalse($assumed);
    }

    public function test_detects_mdy_when_second_field_exceeds_12(): void
    {
        [$format, $assumed] = (new CsvPunchParser)->detectFormat(['03/25/2026 08:05']);

        $this->assertSame(CsvPunchParser::FORMAT_MDY, $format);
        $this->assertFalse($assumed);
    }

    public function test_ambiguous_file_assumes_config_default_instead_of_throwing(): void
    {
        config(['dtr.ambiguous_date_format' => CsvPunchParser::FORMAT_DMY]);

        [$format, $assumed] = (new CsvPunchParser)->detectFormat(['01/08/2026 08:05', '02/09/2026 09:00']);

        $this->assertSame(CsvPunchParser::FORMAT_DMY, $format);
        $this->assertTrue($assumed);
    }

    public function test_mixed_dmy_and_mdy_rows_assume_config_default_instead_of_throwing(): void
    {
        config(['dtr.ambiguous_date_format' => CsvPunchParser::FORMAT_MDY]);

        // 25/03 can only be dmy, 03/25 can only be mdy — genuinely conflicting.
        [$format, $assumed] = (new CsvPunchParser)->detectFormat(['25/03/2026 08:05', '03/25/2026 08:05']);

        $this->assertSame(CsvPunchParser::FORMAT_MDY, $format);
        $this->assertTrue($assumed);
    }

    public function test_no_recognisable_date_still_throws(): void
    {
        $this->expectException(CsvFormatException::class);

        (new CsvPunchParser)->detectFormat(['not-a-date', 'also nonsense']);
    }

    public function test_parses_12_hour_am_pm_values(): void
    {
        $parser = new CsvPunchParser;

        $dt = $parser->parse('01/08/2026 8:05 AM', CsvPunchParser::FORMAT_DMY);
        $this->assertSame('2026-08-01 08:05:00', $dt->toDateTimeString());

        $dt = $parser->parse('01/08/2026 8:05 PM', CsvPunchParser::FORMAT_DMY);
        $this->assertSame('2026-08-01 20:05:00', $dt->toDateTimeString());

        $dt = $parser->parse('01/08/2026 12:00 AM', CsvPunchParser::FORMAT_DMY);
        $this->assertSame('2026-08-01 00:00:00', $dt->toDateTimeString());
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->expectException(CsvFormatException::class);
        $this->expectExceptionCode(CsvPunchParser::CODE_UNPARSABLE);

        // Day 32 is impossible in any format.
        (new CsvPunchParser)->parse('32/08/2026 08:05', CsvPunchParser::FORMAT_DMY);
    }
}
