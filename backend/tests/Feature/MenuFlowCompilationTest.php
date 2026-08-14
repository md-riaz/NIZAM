<?php

namespace Tests\Feature;

use App\Data\FlowData;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\FlowCompiledArtifact;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Flow\FlowArtifactService;
use App\Services\Flow\FlowGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A menu node has to compile into something that actually collects a digit.
 *
 * It previously compiled to a bare transfer to the timeout branch: no playback,
 * no digit collection, so every option the menu advertised was unreachable and a
 * caller was dropped straight into the fallback. The flow still published and
 * still had an active version, which is why nothing caught it.
 */
class MenuFlowCompilationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create(['domain' => 'menu.example.com']);
    }

    public function test_a_menu_with_digit_branches_collects_digits(): void
    {
        $xml = $this->compileMenuFlow();

        $this->assertStringContainsString('_menu.lua', $xml, 'The menu did not compile to the digit-collection helper.');
        $this->assertStringNotContainsString(
            'application="transfer" data="node_',
            $this->menuExtension($xml),
            'The menu still transfers straight past digit collection.'
        );
    }

    public function test_the_compiled_menu_carries_every_digit_branch(): void
    {
        $xml = $this->menuExtension($this->compileMenuFlow());

        // Each configured digit must map to a node target, or that option is dead.
        $this->assertMatchesRegularExpression('/1:node_[0-9a-f-]+/', $xml);
        $this->assertMatchesRegularExpression('/2:node_[0-9a-f-]+/', $xml);
        $this->assertMatchesRegularExpression('/0:node_[0-9a-f-]+/', $xml);
    }

    public function test_the_compiled_menu_passes_the_prompt_timeout_and_retry_budget(): void
    {
        $xml = $this->menuExtension($this->compileMenuFlow());

        $this->assertStringContainsString('welcome.wav', $xml, 'The configured prompt was dropped.');
        $this->assertStringContainsString(' 7 ', $xml, 'The configured timeout was dropped.');
        $this->assertStringContainsString(' 2 ', $xml, 'The configured retry budget was dropped.');
    }

    /**
     * The organization's own context must be used, not a hardcoded one, or the
     * transfer lands in a context where the target extension does not exist.
     */
    public function test_the_compiled_menu_transfers_within_the_organization_context(): void
    {
        $xml = $this->menuExtension($this->compileMenuFlow());

        $this->assertStringContainsString("'menu.example.com'", $xml);
    }

    /**
     * A menu with no digit branches has nothing to collect, so it keeps the old
     * behaviour of falling through rather than answering and waiting in silence.
     */
    public function test_a_menu_without_digit_branches_still_falls_through(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        app(FlowGraphService::class)->updateFlowWithVersion($flow, FlowData::fromArray([
            'name' => $flow->name,
            'publish' => true,
            'version' => [
                'definition' => [
                    'nodes' => [
                        ['id' => 'start', 'type' => 'start', 'name' => 'Start', 'config' => []],
                        ['id' => 'menu', 'type' => 'menu', 'name' => 'Menu', 'config' => ['prompt' => 'p.wav']],
                        ['id' => 'bye', 'type' => 'hangup', 'name' => 'Bye', 'config' => []],
                    ],
                    'edges' => [
                        ['source_node_id' => 'start', 'target_node_id' => 'menu', 'condition' => 'next'],
                        ['source_node_id' => 'menu', 'target_node_id' => 'bye', 'condition' => 'timeout'],
                    ],
                ],
            ],
        ]));

        $xml = $this->compiledXml($flow);

        $this->assertStringNotContainsString('_menu.lua', $xml);
        $this->assertStringContainsString('application="transfer"', $xml);
    }

    private function compileMenuFlow(): string
    {
        $sales = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $support = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $operator = Extension::factory()->create(['organization_id' => $this->organization->id]);

        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        app(FlowGraphService::class)->updateFlowWithVersion($flow, FlowData::fromArray([
            'name' => $flow->name,
            'publish' => true,
            'version' => [
                'definition' => [
                    'nodes' => [
                        ['id' => 'start', 'type' => 'start', 'name' => 'Start', 'config' => []],
                        ['id' => 'menu', 'type' => 'menu', 'name' => 'Main Menu', 'config' => [
                            'prompt' => 'welcome.wav',
                            'timeout' => 7,
                            'max_failures' => 2,
                            'digits' => ['1', '2', '0'],
                        ]],
                        ['id' => 'to-sales', 'type' => 'play_message', 'name' => 'Sales', 'config' => [
                            'prompt' => 'Connecting.',
                            'destination_type' => 'extension',
                            'destination_value' => $sales->id,
                        ]],
                        ['id' => 'to-support', 'type' => 'play_message', 'name' => 'Support', 'config' => [
                            'prompt' => 'Connecting.',
                            'destination_type' => 'extension',
                            'destination_value' => $support->id,
                        ]],
                        ['id' => 'to-operator', 'type' => 'play_message', 'name' => 'Operator', 'config' => [
                            'prompt' => 'Connecting.',
                            'destination_type' => 'extension',
                            'destination_value' => $operator->id,
                        ]],
                        ['id' => 'bye', 'type' => 'hangup', 'name' => 'Bye', 'config' => []],
                    ],
                    'edges' => [
                        ['source_node_id' => 'start', 'target_node_id' => 'menu', 'condition' => 'next'],
                        ['source_node_id' => 'menu', 'target_node_id' => 'to-sales', 'condition' => 'digit_1'],
                        ['source_node_id' => 'menu', 'target_node_id' => 'to-support', 'condition' => 'digit_2'],
                        ['source_node_id' => 'menu', 'target_node_id' => 'to-operator', 'condition' => 'digit_0'],
                        ['source_node_id' => 'menu', 'target_node_id' => 'bye', 'condition' => 'timeout'],
                    ],
                ],
            ],
        ]));

        return $this->compiledXml($flow);
    }

    /**
     * A team ring must transfer within the organization's context too.
     *
     * The helper script hardcoded "default" while these targets are compiled into
     * the organization's context, so answered, no-answer and timeout branches all
     * transferred somewhere their target did not exist.
     */
    public function test_a_team_ring_transfers_within_the_organization_context(): void
    {
        // Team has no factory in this codebase; create() is the established pattern.
        $team = Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        // A team with no members compiles to an empty dial string, which takes the
        // warning path instead of the ring path this case is about.
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => Extension::factory()->create(['organization_id' => $this->organization->id])->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $fallback = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        app(FlowGraphService::class)->updateFlowWithVersion($flow, FlowData::fromArray([
            'name' => $flow->name,
            'publish' => true,
            'version' => [
                'definition' => [
                    'nodes' => [
                        ['id' => 'start', 'type' => 'start', 'name' => 'Start', 'config' => []],
                        ['id' => 'ring', 'type' => 'ring_team', 'name' => 'Ring', 'config' => ['team_id' => $team->id, 'timeout' => 20]],
                        ['id' => 'fallback', 'type' => 'play_message', 'name' => 'Fallback', 'config' => [
                            'prompt' => 'Connecting.',
                            'destination_type' => 'extension',
                            'destination_value' => $fallback->id,
                        ]],
                        ['id' => 'bye', 'type' => 'hangup', 'name' => 'Bye', 'config' => []],
                    ],
                    'edges' => [
                        ['source_node_id' => 'start', 'target_node_id' => 'ring', 'condition' => 'next'],
                        ['source_node_id' => 'ring', 'target_node_id' => 'bye', 'condition' => 'answered'],
                        ['source_node_id' => 'ring', 'target_node_id' => 'fallback', 'condition' => 'no_answer'],
                        ['source_node_id' => 'ring', 'target_node_id' => 'fallback', 'condition' => 'timeout'],
                    ],
                ],
            ],
        ]));

        $xml = $this->compiledXml($flow);

        $this->assertStringContainsString('_team_ring.lua', $xml);
        $this->assertMatchesRegularExpression(
            "/_team_ring\.lua.*'menu\.example\.com'/",
            $xml,
            'The team ring was compiled without the organization context.'
        );
    }

    /**
     * The stored dialplan artifact for the flow's active version.
     */
    private function compiledXml(Flow $flow): string
    {
        $version = $flow->fresh()->activeVersion;
        $this->assertNotNull($version, 'The flow has no active version to compile.');

        $result = app(FlowArtifactService::class)->compileAndStore($version);
        $this->assertTrue($result['success'] ?? false, 'Compilation failed: '.($result['error'] ?? 'unknown'));

        return (string) FlowCompiledArtifact::query()
            ->where('flow_version_id', $version->id)
            ->where('artifact_type', FlowCompiledArtifact::ARTIFACT_TYPE_DIALPLAN_XML)
            ->value('content');
    }

    /**
     * The single compiled extension block for the menu node.
     */
    private function menuExtension(string $xml): string
    {
        $this->assertMatchesRegularExpression('/_menu\.lua/', $xml);

        preg_match('/<extension name="node_[^"]*">(?:(?!<\/extension>).)*_menu\.lua.*?<\/extension>/s', $xml, $matches);

        $this->assertNotEmpty($matches, 'Could not isolate the compiled menu extension.');

        return $matches[0];
    }
}
