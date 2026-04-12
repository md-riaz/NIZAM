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
    function sip_test_digest_response(
        string $username,
        string $realm,
        string $password,
        string $nonce,
        string $method,
        string $uri,
        ?string $qop = null,
        ?string $nonceCount = null,
        ?string $clientNonce = null,
    ): string {
        $ha1 = sip_test_md5($username.':'.$realm.':'.$password);
        $ha2 = sip_test_md5($method.':'.$uri);

        if ($qop !== null && $nonceCount !== null && $clientNonce !== null) {
            return sip_test_md5($ha1.':'.$nonce.':'.$nonceCount.':'.$clientNonce.':'.$qop.':'.$ha2);
        }

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

if (! function_exists('sip_test_build_invite_request')) {
    function sip_test_build_invite_request(
        string $fromExtension,
        string $toExtension,
        string $domain,
        string $localHost,
        int $localPort,
        string $callId,
        int $cseq,
        string $branch,
        ?string $authorization = null,
        string $sdpIp = '127.0.0.1',
    ): string {
        $sdp = "v=0\r\no=NizamScript 53655765 2353687637 IN IP4 ".$sdpIp."\r\ns=-\r\nc=IN IP4 ".$sdpIp."\r\nt=0 0\r\nm=audio 6000 RTP/AVP 0\r\na=rtpmap:0 PCMU/8000\r\n";
        $contentLength = strlen($sdp);

        $headers = [
            'INVITE sip:'.$toExtension.'@'.$domain.' SIP/2.0',
            'Via: SIP/2.0/UDP '.$localHost.':'.$localPort.';rport;branch='.$branch,
            'From: <sip:'.$fromExtension.'@'.$domain.'>;tag=invtag',
            'To: <sip:'.$toExtension.'@'.$domain.'>',
            'Call-ID: '.$callId,
            'CSeq: '.$cseq.' INVITE',
            'Contact: <sip:'.$fromExtension.'@'.$localHost.':'.$localPort.'>',
            'Content-Type: application/sdp',
            'Max-Forwards: 70',
            'Allow: INVITE, ACK, CANCEL, BYE, NOTIFY, REFER, MESSAGE, OPTIONS, INFO, SUBSCRIBE',
            'Content-Length: '.$contentLength,
        ];

        if ($authorization !== null) {
            if (preg_match('/^(Authorization|Proxy-Authorization):/i', $authorization)) {
                $headers[] = $authorization;
            } else {
                $headers[] = 'Proxy-Authorization: '.$authorization;
            }
        }

        return implode("\r\n", $headers)."\r\n\r\n".$sdp;
    }
}

if (! function_exists('sip_test_build_ack_request')) {
    function sip_test_build_ack_request(
        string $fromExtension,
        string $toExtension,
        string $domain,
        string $localHost,
        int $localPort,
        string $callId,
        int $cseq,
        string $branch,
        ?string $toTag = null,
    ): string {
        $toHeader = 'To: <sip:'.$toExtension.'@'.$domain.'>';
        if ($toTag !== null) {
            $toHeader .= ';tag='.$toTag;
        }

        $headers = [
            'ACK sip:'.$toExtension.'@'.$domain.' SIP/2.0',
            'Via: SIP/2.0/UDP '.$localHost.':'.$localPort.';rport;branch='.$branch,
            'From: <sip:'.$fromExtension.'@'.$domain.'>;tag=invtag',
            $toHeader,
            'Call-ID: '.$callId,
            'CSeq: '.$cseq.' ACK',
            'Max-Forwards: 70',
        ];

        return implode("\r\n", $headers)."\r\n\r\n";
    }
}
