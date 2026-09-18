<?php

namespace App\Services\Cdr;

/**
 * A spooled record that could not be read as a call detail record.
 *
 * Kept distinct from a storage failure so the two land in different quarantine
 * directories: a record that will never parse is a different problem from one
 * that parsed and could not be written, and only the second is worth requeuing
 * once the cause is fixed.
 */
class XmlCdrParseException extends \RuntimeException {}
