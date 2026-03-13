<?php

namespace App\Domain\Flow\Contracts;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\NodeResult;

interface NodeHandler
{
    public function execute(array $node, CallContext $context): NodeResult;
}
