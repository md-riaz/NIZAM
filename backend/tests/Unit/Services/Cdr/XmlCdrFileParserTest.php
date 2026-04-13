<?php

namespace Tests\Unit\Services\Cdr;

use App\Services\Cdr\XmlCdrFileParser;
use Tests\TestCase;

class XmlCdrFileParserTest extends TestCase
{
    public function test_it_parses_basic_xml_cdr_fields(): void
    {
        $parser = new XmlCdrFileParser;

        $parsed = $parser->parseString(<<<'XML'
<?xml version="1.0"?>
<cdr>
  <variables>
    <uuid>call-123</uuid>
    <domain_name>demo.example.com</domain_name>
    <caller_id_name>John</caller_id_name>
    <caller_id_number>01712345678</caller_id_number>
    <destination_number>1001</destination_number>
    <start_stamp>2026-04-12 10:00:00</start_stamp>
    <answer_stamp>2026-04-12 10:00:05</answer_stamp>
    <end_stamp>2026-04-12 10:02:00</end_stamp>
    <billsec>115</billsec>
    <hangup_cause>NORMAL_CLEARING</hangup_cause>
  </variables>
</cdr>
XML);

        $this->assertSame('call-123', $parsed['uuid']);
        $this->assertSame('demo.example.com', $parsed['domain']);
        $this->assertSame('01712345678', $parsed['caller_id_number']);
        $this->assertSame('1001', $parsed['destination_number']);
        $this->assertSame(115, $parsed['billsec']);
        $this->assertSame('NORMAL_CLEARING', $parsed['hangup_cause']);
    }
}
