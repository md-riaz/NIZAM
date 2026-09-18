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
            'recording_path' => $this->recordingPath($vars),
            'sip_user_agent' => (string) ($vars->sip_user_agent ?? ''),
            'remote_media_ip' => (string) ($vars->remote_media_ip ?? ''),
            'metadata' => [
                'raw' => $xml,
            ],
        ];
    }

    /**
     * Where the recording for this call ended up, if the record says.
     *
     * There is no single variable for this. FreeSWITCH has several ways to
     * record a call and each leaves the path somewhere different, so the record
     * has to be asked in turn — the same chain FusionPBX and FS PBX both walk,
     * arrived at over years of finding recordings the previous branch missed.
     *
     * A call recorded by `uuid_record` issued over the event socket appears in
     * none of them: nothing writes the path back to the channel. Those are known
     * only to whoever started the recording, which is why an empty answer here
     * must never overwrite a path another writer already stored.
     */
    protected function recordingPath(?SimpleXMLElement $vars): ?string
    {
        if (! $vars) {
            return null;
        }

        $directory = trim((string) ($vars->record_path ?? ''));
        $name = trim((string) ($vars->record_name ?? ''));

        if ($directory !== '' && $name !== '') {
            return rtrim($directory, '/').'/'.$name;
        }

        // A queue recording names its own file.
        $callCentre = trim((string) ($vars->cc_record_filename ?? ''));

        if ($callCentre !== '') {
            return $callCentre;
        }

        // `record_session` leaves its destination as the last application's
        // argument, which is the only trace of it on the channel.
        if (trim((string) ($vars->last_app ?? '')) === 'record_session') {
            $argument = trim((string) ($vars->last_arg ?? ''));

            if ($argument !== '') {
                return $argument;
            }
        }

        foreach (['sofia_record_file', 'conference_recording'] as $variable) {
            $value = trim((string) ($vars->{$variable} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
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
