<?php

namespace App\Rules;

use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

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

    /**
     * The declared destination type is accepted as mixed rather than ?string.
     *
     * This rule is constructed while the rules array is being assembled, before
     * any rule has run, so it sees the raw request input. A client sending
     * `destination_type` as an array would otherwise hit a `?string` parameter
     * and raise a TypeError — a 500 where the answer should simply be that the
     * type is invalid, which the `Rule::in` on that field reports.
     */
    public function __construct(
        private readonly ?Organization $organization,
        private readonly mixed $destinationType,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Shape, presence, and the type field itself are covered by their own
        // rules; this one stays quiet rather than piling a second error onto the
        // same input.
        if (! is_string($value) || $value === '') {
            return;
        }

        // Laravel does not stop at the first failing rule, so a malformed value
        // reaches this rule even though `uuid` already rejected it. Postgres
        // types these keys as `uuid` and raises "invalid input syntax" when
        // compared with a non-UUID, which would turn invalid input into a 500.
        if (! Str::isUuid($value)) {
            return;
        }

        if (! is_string($this->destinationType) || ! in_array($this->destinationType, self::TYPES, true)) {
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
