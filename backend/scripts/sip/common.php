<?php

use RTCKit\SIP\Message;

if (! function_exists('sip_test_build_target_config')) {
    function sip_test_build_target_config(array $extensionData, string $mode = 'docker'): array
    {
        return [
            'host' => $mode === 'host' ? $extensionData['host_target'] : $extensionData['docker_target'],
            'port' => (int) ($mode === 'host' ? $extensionData['host_port'] : $extensionData['internal_port']),
            'domain' => $extensionData['domain'],
        ];
    }
}

if (! function_exists('sip_test_md5')) {
    function sip_test_md5(string $value): string
    {
        return md5($value);
    }
}

if (! function_exists('sip_test_digest_response')) {
    function sip_test_digest_response(string $username, string $realm, string $password, string $nonce, string $method, string $uri): string
    {
        $ha1 = sip_test_md5($username.':'.$realm.':'.$password);
        $ha2 = sip_test_md5($method.':'.$uri);

        return sip_test_md5($ha1.':'.$nonce.':'.$ha2);
    }
}

if (! function_exists('sip_test_parse_auth_header')) {
    function sip_test_parse_auth_header(string $header): array
    {
        preg_match_all('/(\w+)="?([^",]+)"?/', $header, $matches, PREG_SET_ORDER);

        $result = [];
        foreach ($matches as $match) {
            $result[$match[1]] = $match[2];
        }

        return $result;
    }
}

if (! function_exists('sip_test_build_register_request')) {
    function sip_test_build_register_request(
        string $extension,
        string $domain,
        string $localHost,
        int $localPort,
        string $callId,
        int $cseq,
        string $branch,
        ?string $authorization = null,
    ): string {
        $headers = [
            'REGISTER sip:'.$domain.' SIP/2.0',
            'Via: SIP/2.0/UDP '.$localHost.':'.$localPort.';rport;branch='.$branch,
            'From: <sip:'.$extension.'@'.$domain.'>;tag=tag1',
            'To: <sip:'.$extension.'@'.$domain.'>',
            'Call-ID: '.$callId,
            'CSeq: '.$cseq.' REGISTER',
            'Contact: <sip:'.$extension.'@'.$localHost.':'.$localPort.'>',
            'Max-Forwards: 70',
            'Expires: 3600',
            'User-Agent: NizamSipRegisterScript',
        ];

        if ($authorization !== null) {
            $headers[] = 'Authorization: '.$authorization;
        }

        return implode("\r\n", $headers)."\r\n\r\n";
    }
}
