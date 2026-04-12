<?php

require __DIR__.'/bootstrap.php';
require __DIR__.'/common.php';

$extensionNumber = $argv[1] ?? null;
$mode = 'docker';

foreach ($argv as $arg) {
    if ($arg === '--host') {
        $mode = 'host';
    }
}

if (! $extensionNumber) {
    fwrite(STDERR, "Usage: php scripts/sip/register.php <extension> [--host]\n");
    exit(1);
}

$data = sip_test_resolve_extension($extensionNumber);
$config = sip_test_build_target_config($data, $mode);

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($socket === false) {
    fwrite(STDERR, "Failed to create UDP socket\n");
    exit(1);
}

socket_bind($socket, '0.0.0.0', 5062);
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);

$callId = 'register-'.time().'@nizam-script';
$initialRequest = sip_test_build_register_request(
    extension: $data['extension'],
    domain: $config['domain'],
    localHost: $mode === 'host' ? '127.0.0.1' : 'app',
    localPort: 5062,
    callId: $callId,
    cseq: 1,
    branch: 'z9hG4bK-reg1',
);

socket_sendto($socket, $initialRequest, strlen($initialRequest), 0, $config['host'], $config['port']);

echo "Sent initial REGISTER\n";

$buffer = '';
$from = '';
$port = 0;
$bytes = @socket_recvfrom($socket, $buffer, 8192, 0, $from, $port);

if ($bytes === false || $buffer === '') {
    fwrite(STDERR, "No SIP response received\n");
    exit(1);
}

echo strtok($buffer, "\r\n")."\n";

if (! str_contains($buffer, '401 Unauthorized')) {
    fwrite(STDERR, "Expected 401 Unauthorized\n");
    exit(1);
}

preg_match('/WWW-Authenticate:\s*(.*)/i', $buffer, $match);
$authParts = sip_test_parse_auth_header($match[1] ?? '');
$realm = $authParts['realm'] ?? '';
$nonce = $authParts['nonce'] ?? '';
$uri = 'sip:'.$config['domain'];
$response = sip_test_digest_response($data['extension'], $realm, $data['password'], $nonce, 'REGISTER', $uri);
$authorization = 'Digest username="'.$data['extension'].'", realm="'.$realm.'", nonce="'.$nonce.'", uri="'.$uri.'", response="'.$response.'", algorithm=MD5';

$authRequest = sip_test_build_register_request(
    extension: $data['extension'],
    domain: $config['domain'],
    localHost: $mode === 'host' ? '127.0.0.1' : 'app',
    localPort: 5062,
    callId: $callId,
    cseq: 2,
    branch: 'z9hG4bK-reg2',
    authorization: $authorization,
);

socket_sendto($socket, $authRequest, strlen($authRequest), 0, $config['host'], $config['port']);

echo "Sent authenticated REGISTER\n";

$buffer = '';
$bytes = @socket_recvfrom($socket, $buffer, 8192, 0, $from, $port);

if ($bytes === false || $buffer === '') {
    fwrite(STDERR, "No SIP response received after auth\n");
    exit(1);
}

echo strtok($buffer, "\r\n")."\n";

if (! str_contains($buffer, '200 OK')) {
    fwrite(STDERR, "Expected 200 OK\n");
    exit(1);
}

echo "REGISTER verification passed\n";
