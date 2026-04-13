<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\FreeSwitchModuleStatusService;
use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class FreeSwitchModuleStatusServiceTest extends TestCase
{
    public function test_it_parses_module_show_output_into_normalized_status_rows(): void
    {
        $service = new FreeSwitchModuleStatusService;

        $rows = $service->parseShowModulesOutput(<<<'TEXT'
type,name,ikey,filename
api,sofia,mod_sofia,/usr/local/freeswitch/mod/mod_sofia.so
xml_handler,xml_locate,mod_xml_curl,/usr/local/freeswitch/mod/mod_xml_curl.so
TEXT);

        $this->assertInstanceOf(Collection::class, $rows);
        $this->assertCount(4, $rows);
        $this->assertSame(['mod_avmd', 'mod_signalwire', 'mod_sofia', 'mod_xml_curl'], $rows->pluck('name')->all());
        $this->assertSame('running', $rows->firstWhere('name', 'mod_sofia')['status']);
        $this->assertFalse($rows->firstWhere('name', 'mod_sofia')['supports_stop']);
        $this->assertSame('running', $rows->firstWhere('name', 'mod_xml_curl')['status']);
        $this->assertTrue($rows->firstWhere('name', 'mod_xml_curl')['supports_stop']);
        $this->assertSame('not_loaded', $rows->firstWhere('name', 'mod_avmd')['status']);
        $this->assertTrue($rows->firstWhere('name', 'mod_avmd')['supports_start']);
    }

    public function test_it_prefers_json_module_output_when_available(): void
    {
        $freeSwitch = new class extends FreeSwitchCommandService {
            public array $calls = [];

            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->calls[] = [$command, $arguments, $background];

                return [
                    'executed' => true,
                    'response' => <<<TEXT
Content-Type: command/reply
Reply-Text: +OK Success

{"row_count":2,"rows":[{"type":"endpoint","name":"sofia","ikey":"mod_sofia","filename":"/usr/local/freeswitch/mod/mod_sofia.so"},{"type":"xml_handler","name":"xml_locate","ikey":"mod_xml_curl","filename":"/usr/local/freeswitch/mod/mod_xml_curl.so"}]}
TEXT,
                ];
            }
        };

        $service = new FreeSwitchModuleStatusService($freeSwitch);

        $result = $service->list();

        $this->assertSame([['show', ['modules', 'as', 'json'], false]], $freeSwitch->calls);
        $this->assertTrue($result['ok']);
        $rows = $result['data'];
        $this->assertCount(4, $rows);
        $this->assertSame(['mod_avmd', 'mod_signalwire', 'mod_sofia', 'mod_xml_curl'], $rows->pluck('name')->all());
        $this->assertSame('running', $rows->firstWhere('name', 'mod_sofia')['status']);
        $this->assertFalse($rows->firstWhere('name', 'mod_sofia')['supports_start']);
        $this->assertSame('running', $rows->firstWhere('name', 'mod_xml_curl')['status']);
        $this->assertFalse($rows->firstWhere('name', 'mod_xml_curl')['supports_start']);
        $this->assertSame('not_loaded', $rows->firstWhere('name', 'mod_avmd')['status']);
        $this->assertTrue($rows->firstWhere('name', 'mod_avmd')['supports_start']);
    }

    public function test_it_returns_known_modules_as_not_loaded_when_json_probe_has_no_rows(): void
    {
        $freeSwitch = new class extends FreeSwitchCommandService {
            public array $calls = [];

            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->calls[] = [$command, $arguments, $background];

                return [
                    'executed' => true,
                    'response' => 'Content-Type: command/reply',
                ];
            }
        };

        $service = new FreeSwitchModuleStatusService($freeSwitch);

        $result = $service->list();

        $this->assertSame([
            ['show', ['modules', 'as', 'json'], false],
        ], $freeSwitch->calls);
        $this->assertTrue($result['ok']);
        $this->assertCount(4, $result['data']);
        $this->assertSame('not_loaded', $result['data']->firstWhere('name', 'mod_sofia')['status']);
        $this->assertSame('not_loaded', $result['data']->firstWhere('name', 'mod_xml_curl')['status']);
        $this->assertSame('not_loaded', $result['data']->firstWhere('name', 'mod_avmd')['status']);
        $this->assertSame('not_loaded', $result['data']->firstWhere('name', 'mod_signalwire')['status']);
    }

    public function test_it_returns_explicit_failure_state_when_freeswitch_command_fails(): void
    {
        $freeSwitch = new class extends FreeSwitchCommandService {
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                return [
                    'executed' => false,
                    'error' => 'Unable to connect to FreeSWITCH ESL.',
                ];
            }
        };

        $service = new FreeSwitchModuleStatusService($freeSwitch);

        $result = $service->list();

        $this->assertFalse($result['ok']);
        $this->assertInstanceOf(Collection::class, $result['data']);
        $this->assertTrue($result['data']->isEmpty());
        $this->assertSame('esl', $result['source']);
        $this->assertTrue($result['live']);
        $this->assertSame('Unable to connect to FreeSWITCH ESL.', $result['error']);
    }

    public function test_it_loads_a_module_with_the_expected_esl_command(): void
    {
        $freeSwitch = new class extends FreeSwitchCommandService {
            public array $calls = [];

            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->calls[] = [$command, $arguments, $background];

                return ['executed' => true, 'response' => '+OK'];
            }
        };

        $service = new FreeSwitchModuleStatusService($freeSwitch);

        $result = $service->start('mod_xml_curl');

        $this->assertTrue($result['ok']);
        $this->assertSame([['load', ['mod_xml_curl'], false]], $freeSwitch->calls);
    }

    public function test_it_only_unloads_allowlisted_modules(): void
    {
        $freeSwitch = new class extends FreeSwitchCommandService {
            public array $calls = [];

            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->calls[] = [$command, $arguments, $background];

                return ['executed' => true, 'response' => '+OK'];
            }
        };

        $service = new FreeSwitchModuleStatusService($freeSwitch);

        $blocked = $service->stop('mod_sofia');
        $allowed = $service->stop('mod_xml_curl');

        $this->assertFalse($blocked['ok']);
        $this->assertSame('This module cannot be stopped from the platform admin UI.', $blocked['error']);
        $this->assertTrue($allowed['ok']);
        $this->assertSame([['unload', ['mod_xml_curl'], false]], $freeSwitch->calls);
    }

    public function test_it_rejects_invalid_module_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $service = new FreeSwitchModuleStatusService(new class extends FreeSwitchCommandService {});
        $service->start('mod_xml_curl; rm -rf /');
    }
}
