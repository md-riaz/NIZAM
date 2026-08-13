<?php

namespace App\Rules;

use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a DID's destination actually exists in the organization.
 *
 * A number may only route to an extension or a flow — a flow is what decides
 * anything more elaborate (ring group, queue, time condition, voicemail), so the
 * DID itself never needs to name those.
 *
 * `destination_id` was previously only checked for UUID shape. Any well-formed
 * UUID passed, so a number could be saved pointing at a record that does not
 * exist, or at a record of the wrong kind — an extension id declared as a flow.
 * Either way the number silently answered to nothing, and the dialplan compiler
 * has no destination to emit.
 */
class DidDestination implements ValidationRule
{
    /**
     * @var array<int, string>
     */
    public const TYPES = ['extension', 'flow'];

    public function __construct(
        private readonly ?Organization $organization,
        private readonly ?string $destinationType,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Shape and the type field itself are covered by their own rules; this
        // rule stays quiet rather than piling a second error onto the same input.
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! in_array($this->destinationType, self::TYPES, true)) {
            return;
        }

        if (! $this->organization) {
            return;
        }

        $exists = match ($this->destinationType) {
            'extension' => $this->organization->extensions()->whereKey($value)->exists(),
            'flow' => $this->organization->flows()->whereKey($value)->exists(),
        };

        if (! $exists) {
            $fail($this->destinationType === 'extension'
                ? 'The selected extension does not exist in this organization.'
                : 'The selected call flow does not exist in this organization.');
        }
    }
}
