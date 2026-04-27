<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;
use App\Services\Media\MediaControlService;

class PlayMessageNodeHandler implements NodeHandler
{
    public function __construct(
        protected MediaControlService $mediaControlService,
    ) {}

    public function execute(array $node, CallContext $context): NodeResult
    {
        $config = (array) ($node['config'] ?? []);
        $prompt = $config['prompt'] ?? $config['message'] ?? null;

        if (is_string($prompt) && $prompt !== '') {
            $this->mediaControlService->playback($context->callSession, $prompt);
        }

        return NodeResult::transition('next', [
            'prompt' => $prompt,
        ]);
    }
}
