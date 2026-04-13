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
        $document = new SimpleXMLElement($xml);
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
            'direction' => (string) ($vars->direction ?? ''),
            'context' => (string) ($vars->context ?? ''),
            'recording_path' => (string) ($vars->recording_file ?? ''),
            'metadata' => [
                'raw' => $xml,
            ],
        ];
    }
}
