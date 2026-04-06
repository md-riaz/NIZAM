<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;
use App\Services\Media\MediaControlService;

class MenuNodeHandler implements NodeHandler
{
    public function __construct(
        protected MediaControlService $mediaControlService,
    ) {}

    public function execute(array $node, CallContext $context): NodeResult
    {
        $config = (array) ($node['config'] ?? []);
        $digits = (array) ($config['digits'] ?? []);
        $selectedDigit = $context->variable('menu_digit');

        if ($selectedDigit !== null && in_array((string) $selectedDigit, array_map('strval', $digits), true)) {
            return NodeResult::transition('digit_'.$selectedDigit, [
                'digit' => (string) $selectedDigit,
            ]);
        }

        if (! empty($config['prompt'])) {
            $this->mediaControlService->playback($context->callSession, (string) $config['prompt']);
        }

        return NodeResult::wait('menu.selection', [
            'prompt' => $config['prompt'] ?? null,
            'timeout' => $config['timeout'] ?? 5,
            'digits' => $digits,
        ]);
    }
}
