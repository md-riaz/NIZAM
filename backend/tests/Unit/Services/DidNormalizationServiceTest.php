<?php

namespace Tests\Unit\Services;

use App\Services\DidNormalizationService;
use PHPUnit\Framework\TestCase;

class DidNormalizationServiceTest extends TestCase
{
    public function test_already_e164_returns_unchanged(): void
    {
        $this->assertEquals('+15551234567', DidNormalizationService::toE164('+15551234567'));
    }

    public function test_missing_plus_prepends_plus(): void
    {
        $this->assertEquals('+15551234567', DidNormalizationService::toE164('15551234567'));
    }

    public function test_double_zero_prefix_removed(): void
    {
        $this->assertEquals('+15551234567', DidNormalizationService::toE164('0015551234567'));
    }

    public function test_us_international_prefix_011_removed(): void
    {
        $this->assertEquals('+4915551234567', DidNormalizationService::toE164('0114915551234567'));
    }

    public function test_national_number_gets_default_country_code(): void
    {
        $this->assertEquals('+15551234567', DidNormalizationService::toE164('5551234567'));
    }

    public function test_national_number_with_custom_country_code(): void
    {
        $this->assertEquals('+445551234567', DidNormalizationService::toE164('5551234567', '44'));
    }

    public function test_strips_dashes_and_spaces(): void
    {
        $this->assertEquals('+15551234567', DidNormalizationService::toE164('+1 555-123-4567'));
    }

    public function test_strips_parentheses_and_dots(): void
    {
        $this->assertEquals('+15551234567', DidNormalizationService::toE164('+1 (555) 123.4567'));
    }

    public function test_is_e164_with_valid_number(): void
    {
        $this->assertTrue(DidNormalizationService::isE164('+15551234567'));
        $this->assertTrue(DidNormalizationService::isE164('+442071234567'));
    }

    public function test_is_e164_with_invalid_number(): void
    {
        $this->assertFalse(DidNormalizationService::isE164('15551234567'));
        $this->assertFalse(DidNormalizationService::isE164('+0551234567'));
        $this->assertFalse(DidNormalizationService::isE164(''));
    }

    public function test_to_digits_only(): void
    {
        $this->assertEquals('15551234567', DidNormalizationService::toDigitsOnly('+1-555-123-4567'));
        $this->assertEquals('15551234567', DidNormalizationService::toDigitsOnly('+1 (555) 123.4567'));
    }

    public function test_short_international_number(): void
    {
        // 7-digit number with country code already included (e.g. small country)
        $result = DidNormalizationService::toE164('+3712345678');
        $this->assertEquals('+3712345678', $result);
    }
}
