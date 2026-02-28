<?php

namespace App\Http\Controllers;

use App\Services\DialplanCompiler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreeswitchXmlController extends Controller
{
    public function __construct(
        protected DialplanCompiler $compiler
    ) {}

    /**
     * Handle mod_xml_curl requests from FreeSWITCH.
     *
     * FreeSWITCH sends POST requests with section, domain, and other params.
     */
    public function handle(Request $request): Response
    {
        $section = $request->input('section', '');
        $domain = $request->input('domain', '');

        return match ($section) {
            'directory' => $this->handleDirectory($domain),
            'dialplan' => $this->handleDialplan($domain, $request->input('Caller-Destination-Number', '')),
            default => $this->notFoundResponse(),
        };
    }

    protected function handleDirectory(string $domain): Response
    {
        $xml = $this->compiler->compileDirectory($domain);

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    protected function handleDialplan(string $domain, string $destinationNumber): Response
    {
        $xml = $this->compiler->compileDialplan($domain, $destinationNumber);

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    protected function notFoundResponse(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
             .'<document type="freeswitch/xml">'."\n"
             .'  <section name="result">'."\n"
             .'    <result status="not found"/>'."\n"
             .'  </section>'."\n"
             .'</document>';

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }
}
