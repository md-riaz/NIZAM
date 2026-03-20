# SIP Mock Testing

Use the built-in `sip-mock` service to test NIZAM gateway provisioning without real carrier credentials.

## What it does

- Listens on UDP `5070` inside the Docker network
- Responds to `REGISTER` with a real digest auth challenge (`401 Unauthorized`)
- Accepts valid digest auth and returns `200 OK`
- Responds to `OPTIONS`
- Does **not** handle real call media or PSTN routing yet

## Default credentials

- host: `sip-mock`
- port: `5070`
- realm: `sip-mock.local`
- username: `mockuser`
- password: `mockpass`
- transport: `udp`

## Suggested gateway payload

```json
{
  "name": "Mock Carrier",
  "host": "sip-mock",
  "port": 5070,
  "username": "mockuser",
  "password": "mockpass",
  "realm": "sip-mock.local",
  "transport": "udp",
  "is_active": true
}
```

## What to verify

1. Gateway XML file appears under:
   - `storage/freeswitch/sip_profiles/external/v_<gateway_uuid>.xml`
2. Same file is visible in FreeSWITCH container:
   - `/usr/local/freeswitch/conf/sip_profiles/external/`
3. FreeSWITCH sees the gateway:
   - `fs_cli -x "sofia status"`
4. Registration attempts appear:
   - `fs_cli -x "show registrations"`
   - `docker compose logs sip-mock`

## Limitations

This simulator proves:
- gateway XML generation
- config mounting
- Sofia rescan/startgw path
- SIP REGISTER auth flow

It does **not** prove:
- outbound INVITE call completion
- RTP/media
- codec negotiation
- DID delivery
- full carrier behavior
