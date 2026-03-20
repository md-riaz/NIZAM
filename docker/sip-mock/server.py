#!/usr/bin/env python3
import hashlib
import os
import re
import secrets
import socket
import threading
import time
from email.utils import formatdate

HOST = os.getenv('SIP_MOCK_HOST', '0.0.0.0')
PORT = int(os.getenv('SIP_MOCK_PORT', '5070'))
REALM = os.getenv('SIP_MOCK_REALM', 'sip-mock.local')
USERNAME = os.getenv('SIP_MOCK_USERNAME', 'mockuser')
PASSWORD = os.getenv('SIP_MOCK_PASSWORD', 'mockpass')
NONCE_TTL = int(os.getenv('SIP_MOCK_NONCE_TTL', '300'))
SERVER = 'NIZAM SIP Mock/0.1'

nonces = {}
registrations = {}
lock = threading.Lock()


def now_http():
    return formatdate(time.time(), usegmt=True)


def md5(text: str) -> str:
    return hashlib.md5(text.encode()).hexdigest()


def cleanup_nonces():
    while True:
        time.sleep(30)
        cutoff = time.time() - NONCE_TTL
        with lock:
            for nonce, ts in list(nonces.items()):
                if ts < cutoff:
                    nonces.pop(nonce, None)


def parse_message(data: bytes):
    text = data.decode(errors='ignore').replace('\r\n', '\n')
    lines = text.split('\n')
    start = lines[0].strip()
    headers = {}
    body = ''
    in_body = False
    for line in lines[1:]:
        if in_body:
            body += line + '\n'
            continue
        if line.strip() == '':
            in_body = True
            continue
        if ':' in line:
            k, v = line.split(':', 1)
            headers[k.strip().lower()] = v.strip()
    return start, headers, body.rstrip('\n')


def parse_request_uri(start_line: str):
    m = re.match(r'^(\w+)\s+(\S+)\s+SIP/2.0$', start_line)
    if not m:
        return None, None
    return m.group(1).upper(), m.group(2)


def parse_digest(value: str):
    value = value.replace('Digest ', '', 1).strip()
    parts = re.findall(r'(\w+)="?([^",]+)"?', value)
    return {k: v for k, v in parts}


def build_response(code: int, reason: str, headers: dict, body: str = ''):
    lines = [f'SIP/2.0 {code} {reason}']
    base = {
        'Server': SERVER,
        'Date': now_http(),
        'Content-Length': str(len(body.encode())),
    }
    base.update(headers)
    for k, v in base.items():
        lines.append(f'{k}: {v}')
    lines.append('')
    lines.append(body)
    return '\r\n'.join(lines).encode()


def auth_ok(method: str, uri: str, auth_header: str):
    data = parse_digest(auth_header)
    nonce = data.get('nonce')
    if not nonce:
        return False
    with lock:
        if nonce not in nonces:
            return False
    username = data.get('username', '')
    realm = data.get('realm', '')
    response = data.get('response', '')
    qop = data.get('qop')
    nc = data.get('nc')
    cnonce = data.get('cnonce')

    if username != USERNAME or realm != REALM:
        return False

    ha1 = md5(f'{USERNAME}:{REALM}:{PASSWORD}')
    ha2 = md5(f'{method}:{uri}')
    if qop and nc and cnonce:
        expected = md5(f'{ha1}:{nonce}:{nc}:{cnonce}:{qop}:{ha2}')
    else:
        expected = md5(f'{ha1}:{nonce}:{ha2}')
    return expected == response


def handle_register(addr, method, uri, headers, sock):
    auth = headers.get('authorization')
    via = headers.get('via', '')
    to = headers.get('to', '')
    from_h = headers.get('from', '')
    call_id = headers.get('call-id', '')
    cseq = headers.get('cseq', '')
    contact = headers.get('contact', '')
    expires = headers.get('expires', '3600')

    common = {
        'Via': via,
        'To': to,
        'From': from_h,
        'Call-ID': call_id,
        'CSeq': cseq,
    }

    if not auth or not auth_ok(method, uri, auth):
        nonce = secrets.token_hex(16)
        with lock:
            nonces[nonce] = time.time()
        hdrs = dict(common)
        hdrs['WWW-Authenticate'] = f'Digest realm="{REALM}", nonce="{nonce}", algorithm=MD5, qop="auth"'
        sock.sendto(build_response(401, 'Unauthorized', hdrs), addr)
        return

    with lock:
        registrations[call_id] = {
            'contact': contact,
            'addr': addr[0],
            'expires': expires,
            'updated_at': int(time.time()),
        }
    hdrs = dict(common)
    hdrs['Contact'] = contact
    hdrs['Expires'] = expires
    sock.sendto(build_response(200, 'OK', hdrs), addr)


def serve():
    threading.Thread(target=cleanup_nonces, daemon=True).start()
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind((HOST, PORT))
    print(f'SIP mock listening on {HOST}:{PORT} realm={REALM} username={USERNAME}', flush=True)

    while True:
        data, addr = sock.recvfrom(65535)
        start, headers, _ = parse_message(data)
        method, uri = parse_request_uri(start)
        if not method:
            continue

        if method == 'REGISTER':
            handle_register(addr, method, uri, headers, sock)
            continue

        common = {
            'Via': headers.get('via', ''),
            'To': headers.get('to', ''),
            'From': headers.get('from', ''),
            'Call-ID': headers.get('call-id', ''),
            'CSeq': headers.get('cseq', ''),
            'Allow': 'REGISTER, OPTIONS',
        }
        if method == 'OPTIONS':
            sock.sendto(build_response(200, 'OK', common), addr)
        else:
            sock.sendto(build_response(501, 'Not Implemented', common), addr)


if __name__ == '__main__':
    serve()
