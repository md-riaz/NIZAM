<?php

namespace App\Services\Recording;

final class RecordingPolicy
{
    public const INHERIT = 'inherit';

    public const OFF = 'off';

    public const ALL = 'all';

    public const INCOMING = 'incoming';

    public const OUTGOING = 'outgoing';

    public const VALUES = [
        self::INHERIT,
        self::OFF,
        self::ALL,
        self::INCOMING,
        self::OUTGOING,
    ];

    public static function normalize(null|string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::VALUES, true)
            ? $normalized
            : self::INHERIT;
    }

    public static function matchesDirection(string $mode, string $direction): bool
    {
        return match ($mode) {
            self::ALL => true,
            self::INCOMING => $direction === 'inbound',
            self::OUTGOING => $direction === 'outbound',
            default => false,
        };
    }
}
