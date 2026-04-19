<?php

namespace App\Services\Media;

final class GatewayLifecycleAction
{
    public const START = 'start';

    public const RESTART = 'restart';

    public const STOP = 'stop';

    public const RESCAN_ONLY = 'rescan_only';

    public const NOOP = 'noop';
}
