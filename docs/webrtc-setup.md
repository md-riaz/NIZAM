# WebRTC Setup Guide

This guide covers configuring NIZAM's FreeSWITCH-based WebRTC support for browser-based softphones using SIP.js or similar WebRTC clients.

---

## Overview

NIZAM includes a dedicated FreeSWITCH SIP profile for WebRTC that provides:

- **WSS (WebSocket Secure)** signaling on port 7443
- **DTLS-SRTP** for encrypted media
- **Opus codec** prioritized for high-quality audio
- **ICE/STUN** support for NAT traversal
- **API endpoint** to retrieve WebRTC connection parameters per extension

---

## Architecture

```
┌────────────────┐     WSS (7443)      ┌──────────────────┐
│  Browser        │ ───────────────────▶│  FreeSWITCH       │
│  (SIP.js)       │     DTLS-SRTP       │  WSS Profile      │
│                 │ ◀──────────────────▶│                    │
└────────────────┘                      └──────────────────┘
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

# WSS port (must match FreeSWITCH wss.xml profile)
WEBRTC_WSS_PORT=7443

# STUN server for NAT traversal
WEBRTC_STUN_SERVER=stun:stun.l.google.com:19302

# Optional TURN server (required for restrictive NATs)
WEBRTC_TURN_SERVER=
WEBRTC_TURN_USERNAME=
WEBRTC_TURN_PASSWORD=
```

### TLS Certificates

WebRTC **requires** valid TLS certificates for WSS connections. Browsers will reject self-signed certificates.

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
3. **Profile**: Verify WSS profile is loaded: `fs_cli -x "sofia status profile wss"`

### Codec Negotiation Issues

1. **Opus not loaded**: Verify mod_opus is loaded: `fs_cli -x "module_exists mod_opus"`
2. **Codec order**: Check `inbound-codec-prefs` in the WSS profile
3. **Browser support**: All modern browsers support Opus natively

---

## Browser Compatibility

| Browser | WebRTC Support | Opus Codec |
|---------|---------------|------------|
| Chrome 80+ | ✅ | ✅ |
| Firefox 78+ | ✅ | ✅ |
| Safari 14.1+ | ✅ | ✅ |
| Edge 80+ | ✅ | ✅ |
