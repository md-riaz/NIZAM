# WebRTC Setup Guide

This guide covers configuring NIZAM's FreeSWITCH-based WebRTC support for browser-based softphones using SIP.js or similar WebRTC clients.

---

## Overview

NIZAM exposes WebRTC transport on the FreeSWITCH `internal` SIP profile and provides:

- **WSS (WebSocket Secure)** signaling on port 7443
- **WS (WebSocket)** signaling on port 5066 for controlled compatibility cases
- **DTLS-SRTP** for encrypted media
- **Opus codec** prioritized for high-quality audio
- **ICE/STUN** support for NAT traversal
- **API endpoint** to retrieve WebRTC connection parameters per extension

---

## Architecture

```
┌────────────────┐     WSS (7443)      ┌──────────────────────────┐
│  Browser        │ ───────────────────▶│  FreeSWITCH               │
│  (SIP.js)       │     DTLS-SRTP       │  Internal SIP Profile     │
│                 │ ◀──────────────────▶│  + WebSocket Transport    │
└────────────────┘                      └──────────────────────────┘
        │                                       │
        │  GET /api/.../webrtc-config           │  ESL
        ▼                                       ▼
┌────────────────┐                      ┌──────────────────┐
│  NIZAM API     │                      │  SIP Endpoints    │
│  (Laravel)     │                      │  (phones, trunks) │
└────────────────┘                      └──────────────────┘
```

---

## Configuration

### Environment Variables

All WebRTC settings are system-wide (configured in `.env`):

```bash
# Enable WebRTC globally
WEBRTC_ENABLED=true

# WSS port (must match the internal SIP profile WebSocket transport)
WEBRTC_WSS_PORT=7443

# STUN server for NAT traversal
WEBRTC_STUN_SERVER=stun:stun.l.google.com:19302

# Optional TURN server (required for restrictive NATs)
WEBRTC_TURN_SERVER=
WEBRTC_TURN_USERNAME=
WEBRTC_TURN_PASSWORD=
```

### TLS Certificates

NIZAM now exposes two WebRTC TLS modes for the shared `internal` SIP profile WebSocket transport:

- **Trusted/public CA certificates**: recommended for production browser use.
- **Self-signed / development certificates**: useful for labs, staging, and local testing.

Platform admins can review both modes and select the active one from the admin settings page. The selected mode controls which certificate directory is applied to the FreeSWITCH `internal` profile WebSocket transport. This mirrors the operational model used by FusionPBX-style SIP profile settings, while still leaving certificate issuance and file placement under operator control.

#### When to use each mode

- Use **trusted/public CA certificates** when browser clients connect from normal user devices. Browsers require a TLS chain they already trust for WSS.
- Use **self-signed** mode only when you control the client environment and can manually trust the certificate chain on each device.
- In both modes, FreeSWITCH expects these files in the configured certificate directory:
  - `wss.pem`
  - `wss.key`
  - `agent.pem`
  - `cafile.pem`

> **Important**: NIZAM selects the active certificate directory, but it does not issue, renew, or rotate the certificate files stored there.

#### Using Let's Encrypt (Recommended for Production)

```bash
# Install certbot
apt-get install certbot

# Generate certificates
certbot certonly --standalone -d your-domain.com

# Copy to FreeSWITCH certificate directory
cp /etc/letsencrypt/live/your-domain.com/fullchain.pem /usr/local/freeswitch/certs/wss.pem
cp /etc/letsencrypt/live/your-domain.com/privkey.pem /usr/local/freeswitch/certs/wss.key

# Combine for FreeSWITCH (it expects a single agent.pem)
cat /usr/local/freeswitch/certs/wss.pem /usr/local/freeswitch/certs/wss.key > /usr/local/freeswitch/certs/agent.pem
cat /etc/letsencrypt/live/your-domain.com/chain.pem > /usr/local/freeswitch/certs/cafile.pem

# Set permissions
chown -R freeswitch:freeswitch /usr/local/freeswitch/certs/
chmod 640 /usr/local/freeswitch/certs/*.pem /usr/local/freeswitch/certs/*.key
```

#### Using Self-Signed Certificates (Development Only)

```bash
mkdir -p /usr/local/freeswitch/certs
cd /usr/local/freeswitch/certs

# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout wss.key -out wss.pem \
  -subj "/CN=your-domain.com"

cat wss.pem wss.key > agent.pem
cp wss.pem cafile.pem
chown -R freeswitch:freeswitch /usr/local/freeswitch/certs/
```

> **Note**: Self-signed certificates will cause browser security warnings. You may need to navigate to `https://your-domain:7443` and accept the certificate before WebRTC will work.

### Admin workflow

1. Open the platform admin settings page.
2. Leave both modes enabled if you want the operators to switch between them without re-entering values.
3. Set the certificate directory for each mode.
4. Choose the active mode.
5. Save settings and reload the FreeSWITCH `internal` profile if required by your deployment process.

The active mode is also returned by the WebRTC configuration API so browser clients and diagnostics can display which trust model is currently selected.

---

## API Endpoint

### Get WebRTC Configuration

```
GET /api/v1/tenants/{tenant_id}/extensions/{extension_id}/webrtc-config
```

**Response:**

```json
{
  "data": {
    "enabled": true,
    "websocket_url": "wss://your-domain.com:7443",
    "tls_mode": {
      "active": "trusted_ca",
      "modes": {
        "trusted_ca": {
          "key": "trusted_ca",
          "label": "Trusted/public CA certificates",
          "enabled": true,
          "cert_dir": "/usr/local/freeswitch/certs",
          "production_ready": true,
          "summary": "Use browser-trusted certificates for production WSS and WebRTC.",
          "details": "Browsers require a trusted HTTPS and WSS certificate chain for production WebRTC.",
          "expected_files": ["wss.pem", "wss.key", "agent.pem", "cafile.pem"]
        },
        "self_signed": {
          "key": "self_signed",
          "label": "Self-signed / development certificates",
          "enabled": true,
          "cert_dir": "/usr/local/freeswitch/certs/dev",
          "production_ready": false,
          "summary": "Use self-signed certificates for labs, staging, or local testing.",
          "details": "Self-signed certificates require manual trust on each client device.",
          "expected_files": ["wss.pem", "wss.key", "agent.pem", "cafile.pem"]
        }
      }
    },
    "sip_uri": "sip:1001@demo.nizam.local",
    "sip_username": "1001",
    "sip_password": "secret1234",
    "sip_domain": "demo.nizam.local",
    "display_name": "John Doe",
    "ice_servers": [
      {
        "urls": "stun:stun.l.google.com:19302"
      }
    ],
    "codec_prefs": ["OPUS", "PCMU", "PCMA", "G722"]
  }
}
```

---

## SIP.js Integration Example

```html
<!DOCTYPE html>
<html>
<head>
  <title>NIZAM WebRTC Phone</title>
  <script src="https://unpkg.com/sip.js@0.21.2/lib/platform/web/index.js"></script>
</head>
<body>
  <audio id="remoteAudio"></audio>
  <button id="call">Call</button>
  <button id="hangup">Hangup</button>

  <script>
    // Fetch WebRTC config from NIZAM API
    async function getConfig(tenantId, extensionId, token) {
      const res = await fetch(
        `/api/v1/tenants/${tenantId}/extensions/${extensionId}/webrtc-config`,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      return (await res.json()).data;
    }

    async function init() {
      const config = await getConfig('TENANT_ID', 'EXTENSION_ID', 'YOUR_TOKEN');

      const userAgent = new SIP.UserAgent({
        uri: SIP.UserAgent.makeURI(config.sip_uri),
        transportOptions: {
          server: config.websocket_url,
        },
        authorizationUsername: config.sip_username,
        authorizationPassword: config.sip_password,
        displayName: config.display_name,
        sessionDescriptionHandlerFactoryOptions: {
          peerConnectionConfiguration: {
            iceServers: config.ice_servers,
          },
        },
      });

      // Register with FreeSWITCH
      const registerer = new SIP.Registerer(userAgent);
      await userAgent.start();
      await registerer.register();

      console.log('Registered successfully!');
    }

    init();
  </script>
</body>
</html>
```

---

## Firewall Requirements

Ensure these ports are open:

| Port | Protocol | Purpose |
|------|----------|---------|
| 7443 | TCP | WSS (WebSocket Secure) signaling |
| 16384-32768 | UDP | RTP media (audio) |
| 3478 | UDP | STUN (if using external STUN server) |

---

## Troubleshooting

### WSS Connection Fails

1. **Certificate issues**: Ensure TLS certificates are valid and not expired
2. **Port blocked**: Verify port 7443 is open in your firewall
3. **Browser security**: Navigate to `https://your-domain:7443` to accept certificates

### No Audio (One-Way or Silent)

1. **STUN/TURN**: Ensure STUN server is reachable; add TURN server for restrictive NATs
2. **Firewall**: UDP ports 16384-32768 must be open for RTP media
3. **ICE candidates**: Check browser console for ICE connection failures

### Registration Fails

1. **Credentials**: Verify extension username/password via the API
2. **Domain**: Ensure `sip_domain` matches the tenant domain in FreeSWITCH
3. **Profile**: Verify the internal profile is loaded and listening for WebSocket transport: `fs_cli -x "sofia status profile internal"`

### Codec Negotiation Issues

1. **Opus not loaded**: Verify mod_opus is loaded: `fs_cli -x "module_exists mod_opus"`
2. **Codec order**: Check `inbound-codec-prefs` in the internal profile
3. **Browser support**: All modern browsers support Opus natively

---

## Browser Compatibility

| Browser | WebRTC Support | Opus Codec |
|---------|---------------|------------|
| Chrome 80+ | ✅ | ✅ |
| Firefox 78+ | ✅ | ✅ |
| Safari 14.1+ | ✅ | ✅ |
| Edge 80+ | ✅ | ✅ |
