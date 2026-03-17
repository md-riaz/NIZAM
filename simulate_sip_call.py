#!/usr/bin/env python3
import socket
import time
import sys
import os
import subprocess

target_ip = "172.23.0.7"
target_port = 5080
local_ip = "172.23.0.1"
local_port = 5062
caller_id = "+15559998888"
dest_number = "+14155551234"

call_id = f"test-call-{int(time.time())}@172.23.0.1"
branch = f"z9hG4bK-{int(time.time())}"

sdp_body = f"v=0\r\no=user1 53655765 2353687637 IN IP4 {local_ip}\r\ns=-\r\nc=IN IP4 {local_ip}\r\nt=0 0\r\nm=audio 6000 RTP/AVP 0\r\na=rtpmap:0 PCMU/8000\r\n"
content_length = len(sdp_body)

sip_invite = (
    f"INVITE sip:{dest_number}@demo.nizam.local:{target_port} SIP/2.0\r\n"
    f"Via: SIP/2.0/UDP {local_ip}:{local_port};rport;branch={branch}\r\n"
    f"Max-Forwards: 70\r\n"
    f"From: \"Test Caller\" <sip:{caller_id}@{local_ip}>;tag=1928301774\r\n"
    f"To: <sip:{dest_number}@demo.nizam.local:{target_port}>\r\n"
    f"Call-ID: {call_id}\r\n"
    f"CSeq: 1 INVITE\r\n"
    f"Contact: <sip:{caller_id}@{local_ip}:{local_port}>\r\n"
    f"Allow: INVITE, ACK, CANCEL, OPTIONS, BYE, REFER, SUBSCRIBE, NOTIFY, INFO, PUBLISH, MESSAGE\r\n"
    f"Supported: replaces, timer\r\n"
    f"Content-Type: application/sdp\r\n"
    f"Content-Length: {content_length}\r\n"
    f"\r\n"
    f"{sdp_body}"
)

print(f"Sending SIP INVITE to {dest_number}...")
sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock.bind((local_ip, local_port))
sock.settimeout(10.0)

sock.sendto(sip_invite.encode('utf-8'), (target_ip, target_port))

try:
    while True:
        data, addr = sock.recvfrom(4096)
        msg = data.decode('utf-8', errors='ignore')
        lines = msg.split('\r\n')
        status_line = lines[0] if lines else ""
        print(f"<-- {status_line}")
        
        if "200 OK" in status_line and "CSeq: 1 INVITE" in msg:
            print(">>> Call answered! Sending ACK...")
            
            # Find the To tag
            to_tag = ""
            for line in lines:
                if line.startswith("To:") or line.startswith("t:"):
                    parts = line.split(";")
                    for p in parts:
                        if p.startswith("tag="):
                            to_tag = p
            
            sip_ack = (
                f"ACK sip:{dest_number}@{target_ip}:{target_port} SIP/2.0\r\n"
                f"Via: SIP/2.0/UDP {local_ip}:{local_port};rport;branch={branch}-ack\r\n"
                f"Max-Forwards: 70\r\n"
                f"From: \"Test Caller\" <sip:{caller_id}@{local_ip}>;tag=1928301774\r\n"
                f"To: <sip:{dest_number}@{target_ip}:{target_port}>;{to_tag}\r\n"
                f"Call-ID: {call_id}\r\n"
                f"CSeq: 1 ACK\r\n"
                f"Contact: <sip:{caller_id}@{local_ip}:{local_port}>\r\n"
                f"Content-Length: 0\r\n"
                f"\r\n"
            )
            sock.sendto(sip_ack.encode('utf-8'), (target_ip, target_port))
            
            # Wait to let media/dialplan run
            time.sleep(3)
            
            print(">>> Hanging up...")
            sip_bye = (
                f"BYE sip:{dest_number}@{target_ip}:{target_port} SIP/2.0\r\n"
                f"Via: SIP/2.0/UDP {local_ip}:{local_port};rport;branch={branch}-bye\r\n"
                f"Max-Forwards: 70\r\n"
                f"From: \"Test Caller\" <sip:{caller_id}@{local_ip}>;tag=1928301774\r\n"
                f"To: <sip:{dest_number}@{target_ip}:{target_port}>;{to_tag}\r\n"
                f"Call-ID: {call_id}\r\n"
                f"CSeq: 2 BYE\r\n"
                f"Contact: <sip:{caller_id}@{local_ip}:{local_port}>\r\n"
                f"Content-Length: 0\r\n"
                f"\r\n"
            )
            sock.sendto(sip_bye.encode('utf-8'), (target_ip, target_port))
            break
            
        elif "404 Not Found" in status_line:
            print(">>> Number not found in dialplan.")
            break
            
        elif any(code in status_line for code in ["486", "480", "500", "503", "603", "400", "401"]):
            print(f">>> Call failed or rejected: {status_line.split()[0:3]}")
            break

except socket.timeout:
    print("\nTimeout waiting for response.")
except KeyboardInterrupt:
    print("\nInterrupted.")
finally:
    sock.close()
