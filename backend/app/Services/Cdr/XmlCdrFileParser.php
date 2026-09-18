<?php

namespace App\Services\Cdr;

use SimpleXMLElement;

class XmlCdrFileParser
{
    public function parseFile(string $path): array
    {
        return $this->parseString((string) file_get_contents($path));
    }

    public function parseString(string $xml): array
    {
        $document = $this->load($xml);
        $vars = $document->variables;

        return [
            'uuid' => (string) ($vars->uuid ?? ''),
            'domain' => (string) ($vars->domain_name ?? ''),
            'caller_id_name' => (string) ($vars->caller_id_name ?? ''),
            'caller_id_number' => (string) ($vars->caller_id_number ?? ''),
            'destination_number' => (string) ($vars->destination_number ?? ''),
            'start_stamp' => (string) ($vars->start_stamp ?? ''),
            'answer_stamp' => (string) ($vars->answer_stamp ?? ''),
            'end_stamp' => (string) ($vars->end_stamp ?? ''),
            'billsec' => (int) ($vars->billsec ?? 0),
            'hangup_cause' => (string) ($vars->hangup_cause ?? ''),
            // `call_direction` is what the dialplan declares the call to be.
            // `direction` is the channel's own direction, which is "inbound" for
            // every a-leg regardless of where the call was going, so it only
            // serves as a fallback for dialplans compiled before the declaration
            // was added.
            'direction' => (string) ($vars->call_direction ?? $vars->direction ?? ''),
            'context' => (string) ($vars->context ?? ''),
            'recording_path' => (string) ($vars->recording_file ?? ''),
            'sip_user_agent' => (string) ($vars->sip_user_agent ?? ''),
            'remote_media_ip' => (string) ($vars->remote_media_ip ?? ''),
            'metadata' => [
                'raw' => $xml,
            ],
        ];
    }

    /**
     * Parse the document, raising rather than returning a half-usable object.
     *
     * `SimpleXMLElement` emits warnings and throws on malformed input, but a
     * record truncated mid-write can also parse into something structurally valid
     * and semantically empty. Both have to reach the caller as a failure so the
     * file is retried or quarantined instead of being written as a blank record.
     */
    protected function load(string $xml): SimpleXMLElement
    {
        $xml = trim($xml);

        if ($xml === '') {
            throw new XmlCdrParseException('XML CDR is empty.');
        }

        // mod_xml_cdr url-encodes the payload when `encode` is true.
        if (str_starts_with($xml, '%')) {
            $xml = urldecode($xml);
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

            if ($document === false) {
                $error = libxml_get_last_error();

                throw new XmlCdrParseException(sprintf(
                    'XML CDR could not be parsed: %s',
                    $error ? trim($error->message) : 'unknown error'
                ));
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
