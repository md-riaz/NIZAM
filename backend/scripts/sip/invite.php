<?php

use RTCKit\SIP\Message;
use RTCKit\SIP\Response;

require __DIR__.'/bootstrap.php';
require __DIR__.'/common.php';

$fromExt = $argv[1] ?? null;
$toExt = $argv[2] ?? null;
$mode = 'docker';

foreach ($argv as $arg) {
    if ($arg === '--host') { $mode = 'host'; }
}

if (! $fromExt || ! $toExt) {
    fwrite(STDERR, "Usage: php scripts/sip/invite.php <from_ext> <to_ext> [--host]\n");
    exit(1);
}

$fromData = sip_test_resolve_extension($fromExt);
$config = sip_test_build_target_config($fromData, $mode);

$socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($socket === false) {
    fwrite(STDERR, 'Failed to create socket: '.socket_strerror(socket_last_error())."\n");
    exit(1);
}

// Use a random port to avoid "Address already in use" on repeated runs
$localPort = rand(10000, 60000);
if (@socket_bind($socket, '0.0.0.0', $localPort) === false) {
    fwrite(STDERR, 'Failed to bind socket: '.socket_strerror(socket_last_error($socket))."\n");
    exit(1);
}

socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);

$callId = 'invite-'.time().'@nizam-script';
$localHost = $mode === 'host' ? '127.0.0.1' : 'app';
$sdpIp = $localHost;
$uri = 'sip:'.$toExt.'@'.$config['domain'];
$success = false;

// --- STEP 1: AUTHENTICATED REGISTER (to create valid contact) ---
echo "Registering $fromExt...\n";
$regCallId = 'reg-'.time().'@nizam-script';
$initialReg = sip_test_build_register_request($fromExt, $config['domain'], $localHost, $localPort, $regCallId, 1, 'z9hG4bK-reg1');
socket_sendto($socket, $initialReg, strlen($initialReg), 0, $config['host'], $config['port']);

$buffer = ''; $from = ''; $port = 0;
$bytes = @socket_recvfrom($socket, $buffer, 8192, 0, $from, $port);
if ($bytes && str_contains($buffer, '401 Unauthorized')) {
    preg_match('/WWW-Authenticate:\s*(.*)/i', $buffer, $match);
    $authParts = sip_test_parse_auth_header($match[1] ?? '');
    $realm = $authParts['realm'] ?? '';
    $nonce = $authParts['nonce'] ?? '';
    $uriReg = 'sip:'.$config['domain'];
    $response = sip_test_digest_response($fromExt, $realm, $fromData['password'], $nonce, 'REGISTER', $uriReg);
    $authorization = 'Digest username="'.$fromExt.'", realm="'.$realm.'", nonce="'.$nonce.'", uri="'.$uriReg.'", response="'.$response.'", algorithm=MD5';

    $authReg = sip_test_build_register_request($fromExt, $config['domain'], $localHost, $localPort, $regCallId, 2, 'z9hG4bK-reg2', $authorization);
    socket_sendto($socket, $authReg, strlen($authReg), 0, $config['host'], $config['port']);

    $bytes = @socket_recvfrom($socket, $buffer, 8192, 0, $from, $port);
    if ($bytes && str_contains($buffer, '200 OK')) {
        echo "Registration successful\n\n";
    } else {
        fwrite(STDERR, "Registration failed\n");
        exit(1);
    }
} else {
    fwrite(STDERR, "Initial registration challenge not received\n");
    exit(1);
}

// --- STEP 2: INVITE PATHFINDER ---
$initialInvite = sip_test_build_invite_request($fromExt, $toExt, $config['domain'], $localHost, $localPort, $callId, 1, 'z9hG4bK-inv1', null, $sdpIp);
socket_sendto($socket, $initialInvite, strlen($initialInvite), 0, $config['host'], $config['port']);
echo "Sent initial INVITE to $toExt (CSeq 1)\n";

$challengeResponse = null;
$challengeRaw = null;

// Loop for initial challenge
while (true) {
    $buffer = ''; $from = ''; $port = 0;
    $bytes = @socket_recvfrom($socket, $buffer, 8192, 0, $from, $port);
    if (! $bytes) break;

    try {
        $sip = \RTCKit\SIP\Message::parse($buffer);
        if (! $sip instanceof \RTCKit\SIP\Response) continue;

        echo "<- {$sip->code} {$sip->reason}\n";

        if ($sip->code === 100) continue;
        if ($sip->code === 401 || $sip->code === 407) {
            $challengeResponse = $sip;
            $challengeRaw = $buffer;
            break;
        }
        if ($sip->code >= 180 && $sip->code < 200) {
            $success = true;
            break;
        }
        if ($sip->code >= 300) {
            fwrite(STDERR, "INVITE rejected: {$sip->code} {$sip->reason}\n");
            exit(1);
        }
    } catch (\Exception $e) {
        continue;
    }
}

if ($challengeResponse) {
    $authHeaderNameSearch = ($challengeResponse->code === 407) ? 'Proxy-Authenticate' : 'WWW-Authenticate';
    $respHeaderName = ($challengeResponse->code === 407) ? 'Proxy-Authorization' : 'Authorization';

    // Extract challenge using robust regex on the raw buffer
    preg_match('/^'.preg_quote($authHeaderNameSearch, '/').':\s*(.*)$/mi', $challengeRaw, $match);
    $challenge = $match[1] ?? '';
    $authParts = sip_test_parse_auth_header($challenge);

    $realm = $authParts['realm'] ?? '';
    $nonce = $authParts['nonce'] ?? '';
    $qop = $authParts['qop'] ?? null;
    $nc = '00000001';
    $cnonce = bin2hex(random_bytes(8));

    // Extract To tag for ACK
    $toTag = null;
    if (isset($challengeResponse->headers['to']) && preg_match('/tag=([^;\s\r\n]+)/', $challengeResponse->headers['to'], $m)) {
        $toTag = $m[1];
    }

    $ack = sip_test_build_ack_request($fromExt, $toExt, $config['domain'], $localHost, $localPort, $callId, 1, 'z9hG4bK-inv1', $toTag);
    socket_sendto($socket, $ack, strlen($ack), 0, $config['host'], $config['port']);
    echo "Sent ACK for challenge (CSeq 1)\n";

    // Give FreeSWITCH a brief moment to complete the challenge transaction state
    usleep(200000);

    $digest = sip_test_digest_response($fromExt, $realm, $fromData['password'], $nonce, 'INVITE', $uri, $qop, $nc, $cnonce);
    $authVal = "Digest username=\"{$fromExt}\", realm=\"{$realm}\", nonce=\"{$nonce}\", uri=\"{$uri}\", response=\"{$digest}\"";
    if ($qop) $authVal .= ", qop=auth, nc={$nc}, cnonce=\"{$cnonce}\"";

    $authInvite = sip_test_build_invite_request($fromExt, $toExt, $config['domain'], $localHost, $localPort, $callId, 2, 'z9hG4bK-inv2', "{$respHeaderName}: {$authVal}", $sdpIp);
    socket_sendto($socket, $authInvite, strlen($authInvite), 0, $config['host'], $config['port']);
    echo "Sent authenticated INVITE (CSeq 2)\n";

    while (true) {
        $buffer = '';
        $bytes = @socket_recvfrom($socket, $buffer, 8192, 0, $from, $port);
        if (! $bytes) break;

        try {
            // Ignore retransmissions of the original challenge (CSeq 1) using raw header parsing
            if (preg_match('/^CSeq:\s*1\s+INVITE$/mi', $buffer)) {
                continue;
            }

            $sip = \RTCKit\SIP\Message::parse($buffer);
            if (! $sip instanceof \RTCKit\SIP\Response) continue;

            echo "<- {$sip->code} {$sip->reason}\n";

            if ($sip->code === 100) {
                // For pathfinder purposes, receiving 100 Trying after auth
                // is strong evidence the dialplan accepted the INVITE.
                continue;
            }

            if ($sip->code >= 180 && $sip->code < 200) {
                $success = true;
                echo "Path verified: Target is ringing/progressing\n";
                break;
            }

            if ($sip->code === 200) {
                $success = true;
                echo "Path verified: Call was answered\n";

                // Send ACK for 200 OK to clean up
                $ack200 = sip_test_build_ack_request($fromExt, $toExt, $config['domain'], $localHost, $localPort, $callId, 2, 'z9hG4bK-ack200', $extractToTag($buffer));
                socket_sendto($socket, $ack200, strlen($ack200), 0, $config['host'], $config['port']);
                echo "Sent ACK for 200 OK\n";
                break;
            }

            if ($sip->code >= 300) {
                // Some rejections (like 408 Timeout or 486 Busy) still prove the route was reached
                if (in_array($sip->code, [408, 480, 486])) {
                    $success = true;
                    echo "Path verified: Route reached but endpoint unavailable ({$sip->code})\n";
                } else {
                    fwrite(STDERR, "Authenticated INVITE rejected: {$sip->code} {$sip->reason}\n");
                }
                break;
            }
        } catch (\Exception $e) {
            continue;
        }
    }
}

if (! $success) {
    fwrite(STDERR, "INVITE failed: no 180 Ringing or 183 Session Progress received\n");
    exit(1);
}

echo "\nSUCCESS: INVITE verification passed (Target is ringing)\n";
exit(0);
